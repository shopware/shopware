<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\AppInfo;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceSourceResolver;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * @internal
 */
#[Package('framework')]
class ServiceSourceResolverTest extends TestCase
{
    private Filesystem $io;

    private string $root;

    private Client&Stub $client;

    private LoggerInterface $logger;

    private TemporaryDirectoryFactory $directoryFactory;

    private AppInfo $appInfo;

    private ServiceSourceResolver $source;

    protected function setUp(): void
    {
        $this->io = new Filesystem();
        $this->root = Path::join((string) realpath(sys_get_temp_dir()), Uuid::randomHex());
        $this->client = static::createStub(Client::class);
        $this->logger = new NullLogger();
        $this->directoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $this->directoryFactory->method('path')->willReturn($this->root);
        $this->appInfo = new AppInfo('TestService', '1.0.0', 'abc123', '1.0.0-abc123', 'https://example.com/service.zip', ['service_consent']);
        $this->source = $this->resolver($this->io);
    }

    protected function tearDown(): void
    {
        $this->io->remove($this->root);
    }

    public function testDownloadVersionThrowsExceptionOnFileWriteError(): void
    {
        $this->io->dumpFile($this->path('TestService/manifest.xml'), 'old');
        $this->expectDownload();
        $underlying = new IOException('Write failed');
        $io = $this->createPartialMock(Filesystem::class, ['appendToFile']);
        $io->expects($this->once())->method('appendToFile')->willThrowException($underlying);

        $this->expectExceptionObject(AppException::cannotMountAppFilesystem(
            'TestService',
            ServiceException::cannotWriteAppToDestination($this->path('TestService'), $underlying)
        ));

        try {
            $this->resolver($io)->filesystemForVersion($this->appInfo);
        } finally {
            static::assertSame('old', $this->io->readFile($this->path('TestService/manifest.xml')));
            static::assertSame(['TestService'], $this->entriesInRoot());
        }
    }

    public function testFilesFromThePreviousRevisionAreRemoved(): void
    {
        $this->io->dumpFile($this->path('TestService/manifest.xml'), 'old');
        $this->io->dumpFile($this->path('TestService/Resources/config/custom-fields.xml'), '<custom-fields/>');
        $this->expectDownload();

        $filesystem = $this->source->filesystemForVersion($this->appInfo);

        static::assertSame($this->path('TestService'), $filesystem->location);
        static::assertStringContainsString('<name>TestService</name>', $filesystem->read('manifest.xml'));
        static::assertFileDoesNotExist($this->path('TestService/Resources/config/custom-fields.xml'));
        static::assertSame(['TestService'], $this->entriesInRoot());
    }

    public function testPreviousRevisionSurvivesAnInterruptedDownload(): void
    {
        $this->io->dumpFile($this->path('TestService/manifest.xml'), 'old');
        $underlying = new IOException('Download interrupted');
        $this->client->method('fetchServiceZip')
            ->willReturnCallback(static function () use ($underlying): \Generator {
                yield new DataChunk(content: 'partial zip');
                throw $underlying;
            });

        $this->expectExceptionObject(AppException::cannotMountAppFilesystem(
            'TestService',
            ServiceException::cannotWriteAppToDestination($this->path('TestService'), $underlying)
        ));

        try {
            $this->source->filesystemForVersion($this->appInfo);
        } finally {
            static::assertSame('old', $this->io->readFile($this->path('TestService/manifest.xml')));
            static::assertSame(['TestService'], $this->entriesInRoot());
        }
    }

    public function testPreviousRevisionSurvivesAnInvalidArchive(): void
    {
        $this->io->dumpFile($this->path('TestService/manifest.xml'), 'old');
        $this->client->method('fetchServiceZip')->willReturn($this->createChunkGenerator(['not-a-zip-archive']));
        $this->expectException(AppException::class);

        try {
            $this->source->filesystemForVersion($this->appInfo);
        } catch (AppException $exception) {
            $cause = $exception->getPrevious();
            static::assertInstanceOf(PluginException::class, $cause);
            static::assertSame(PluginException::CANNOT_EXTRACT_ZIP_INVALID_ZIP, $cause->getErrorCode());
            throw $exception;
        } finally {
            static::assertSame('old', $this->io->readFile($this->path('TestService/manifest.xml')));
            static::assertSame(['TestService'], $this->entriesInRoot());
        }
    }

    public function testPreviousRevisionIsRestoredWhenPromotionFails(): void
    {
        $this->io->dumpFile($this->path('TestService/manifest.xml'), 'old');
        $this->expectDownload();
        $underlying = new IOException('Promotion failed');
        $io = $this->createPartialMock(Filesystem::class, ['rename']);
        $io->expects($this->exactly(3))->method('rename')->willReturnCallback(function (string $origin, string $target) use ($underlying): void {
            if (str_contains($origin, '.tmp-')) {
                throw $underlying;
            }

            $this->io->rename($origin, $target);
        });

        $this->expectExceptionObject(AppException::cannotMountAppFilesystem(
            'TestService',
            ServiceException::cannotWriteAppToDestination($this->path('TestService'), $underlying)
        ));

        try {
            $this->resolver($io)->filesystemForVersion($this->appInfo);
        } finally {
            static::assertSame('old', $this->io->readFile($this->path('TestService/manifest.xml')));
            static::assertSame(['TestService'], $this->entriesInRoot());
        }
    }

    #[DataProvider('cleanupFailureProvider')]
    public function testCleanupFailureDoesNotFailSuccessfulReplacement(string $suffix): void
    {
        $this->io->dumpFile($this->path('TestService/manifest.xml'), 'old');
        $this->expectDownload();
        $underlying = new IOException('Cleanup failed');
        $io = $this->createPartialMock(Filesystem::class, ['remove']);
        $io->expects($this->exactly(2))->method('remove')->willReturnCallback(function (string $path) use ($suffix, $underlying): void {
            if (str_contains($path, $suffix)) {
                throw $underlying;
            }

            $this->io->remove($path);
        });
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())->method('warning')
            ->with('Cannot remove temporary service directory', static::callback(
                static fn (array $context): bool => $context['exception'] === $underlying && str_contains($context['path'], $suffix)
            ));

        $filesystem = $this->resolver($io)->filesystemForVersion($this->appInfo);

        static::assertSame($this->path('TestService'), $filesystem->location);
        static::assertStringContainsString('<name>TestService</name>', $filesystem->read('manifest.xml'));
        static::assertCount(2, $this->entriesInRoot());
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function cleanupFailureProvider(): \Generator
    {
        yield 'backup removal failure preserves the successful replacement' => ['.bak-'];
        yield 'staging removal failure preserves the successful replacement' => ['.tmp-'];
    }

    public function testCleanupFailureDoesNotMaskTheDownloadError(): void
    {
        $this->io->dumpFile($this->path('TestService/manifest.xml'), 'old');
        $underlying = ServiceException::missingAppVersionInformation('version');
        $this->client->method('fetchServiceZip')->willReturnCallback(static function () use ($underlying): \Generator {
            yield new DataChunk(content: 'partial zip');
            throw $underlying;
        });
        $cleanupError = new IOException('Cleanup failed');
        $io = $this->createPartialMock(Filesystem::class, ['remove']);
        $io->expects($this->once())->method('remove')->willThrowException($cleanupError);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())->method('warning');
        $this->expectExceptionObject(AppException::cannotMountAppFilesystem(
            'TestService',
            ServiceException::cannotWriteAppToDestination($this->path('TestService'), $underlying)
        ));

        try {
            $this->resolver($io)->filesystemForVersion($this->appInfo);
        } catch (AppException $exception) {
            static::assertSame($underlying, $exception->getPrevious()?->getPrevious());
            throw $exception;
        } finally {
            static::assertSame('old', $this->io->readFile($this->path('TestService/manifest.xml')));
        }
    }

    /**
     * @param string[] $chunks
     *
     * @return \Generator<ChunkInterface>
     */
    private function createChunkGenerator(array $chunks): \Generator
    {
        foreach ($chunks as $chunkContent) {
            $chunk = $this->createMock(ChunkInterface::class);
            $chunk->expects($this->once())
                ->method('getContent')
                ->willReturn($chunkContent);
            yield $chunk;
        }
    }

    private function resolver(Filesystem $io): ServiceSourceResolver
    {
        return new ServiceSourceResolver($this->client, $this->directoryFactory, new AppExtractor(new AppArchiveValidator()), $io, $this->logger);
    }

    private function expectDownload(): void
    {
        $this->client->method('fetchServiceZip')->willReturnCallback(function (string $url): \Generator {
            static::assertSame($this->appInfo->zipUrl, $url);

            return $this->createChunkGenerator(str_split($this->io->readFile(__DIR__ . '/_fixtures/TestService.zip'), 64));
        });
    }

    private function path(string $path): string
    {
        return Path::join($this->root, $path);
    }

    /**
     * @return list<string>
     */
    private function entriesInRoot(): array
    {
        $entries = scandir($this->root);
        static::assertIsArray($entries);

        return array_values(array_diff($entries, ['.', '..']));
    }
}
