<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
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
#[CoversClass(ServiceSourceResolver::class)]
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

    public function testName(): void
    {
        $source = new ServiceSourceResolver(
            static::createStub(Client::class),
            new TemporaryDirectoryFactory(),
            static::createStub(AppExtractor::class),
            static::createStub(Filesystem::class),
            new NullLogger()
        );
        static::assertSame('service', $source->name());
    }

    public function testSupportsOnlyConsidersServiceTypes(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setSourceType('service');

        $source = new ServiceSourceResolver(
            static::createStub(Client::class),
            new TemporaryDirectoryFactory(),
            static::createStub(AppExtractor::class),
            static::createStub(Filesystem::class),
            new NullLogger()
        );

        static::assertTrue($source->supports($app));

        $app->setSourceType('not-supported');

        static::assertFalse($source->supports($app));
    }

    public function testSupportSelfManagedManifestsWithHttpUrls(): void
    {
        $manifest = static::createStub(Manifest::class);
        $manifest->method('getPath')->willReturn('https://example.com');

        $metadata = Metadata::fromArray([
            'name' => 'TestApp',
            'label' => [],
            'author' => 'Shopware',
            'copyright' => 'Shopware',
            'license' => 'Shopware',
            'version' => '1.0',
        ]);

        $metadata->setSelfManaged(true);

        $manifest->method('getMetadata')->willReturn($metadata);

        $source = new ServiceSourceResolver(
            static::createStub(Client::class),
            new TemporaryDirectoryFactory(),
            static::createStub(AppExtractor::class),
            static::createStub(Filesystem::class),
            new NullLogger()
        );

        static::assertTrue($source->supports($manifest));
    }

    public function testFilesystemForVersion(): void
    {
        $client = $this->createMock(Client::class);
        $temporaryDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $appExtractor = $this->createMock(AppExtractor::class);
        $filesystem = $this->createMock(Filesystem::class);

        $this->successfulDownloadVersionCommonExpectations(
            $client,
            $temporaryDirectoryFactory,
            $appExtractor,
            $filesystem,
            'TestService',
            'https://example.com/app.zip',
            ['chunk1', 'chunk2', 'chunk3']
        );

        $source = new ServiceSourceResolver($client, $temporaryDirectoryFactory, $appExtractor, $filesystem, new NullLogger());

        $appInfo = new AppInfo(
            'TestService',
            '1.0.0',
            'abc123',
            '1.0.0-abc123',
            'https://example.com/app.zip',
            ['service_consent'],
            'sha256',
            '6.6.0.0'
        );

        $result = $source->filesystemForVersion($appInfo);

        static::assertSame('/tmp/test/TestService', $result->location);
    }

    public function testFilesystemWhenAppExists(): void
    {
        $client = $this->createMock(Client::class);
        $temporaryDirectoryFactory = $this->createMock(TemporaryDirectoryFactory::class);
        $appExtractor = $this->createMock(AppExtractor::class);
        $filesystem = $this->createMock(Filesystem::class);

        $temporaryDirectoryFactory->expects($this->once())
            ->method('path')
            ->willReturn('/tmp/test');

        $filesystem->expects($this->once())
            ->method('exists')
            ->with('/tmp/test/TestService')
            ->willReturn(true);

        // Should not call download methods when app exists
        $client->expects($this->never())->method('fetchServiceZip');
        $appExtractor->expects($this->never())->method('extract');

        $source = new ServiceSourceResolver($client, $temporaryDirectoryFactory, $appExtractor, $filesystem, new NullLogger());

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestService');
        $app->setSourceType('service');

        $result = $source->filesystem($app);

        static::assertSame('/tmp/test/TestService', $result->location);
    }

    public function testAppIsDownloadedIfItDoesNotExistOnFilesystem(): void
    {
        $client = $this->createMock(Client::class);
        $temporaryDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $appExtractor = $this->createMock(AppExtractor::class);
        $filesystem = $this->createMock(Filesystem::class);

        $filesystem->expects($this->exactly(2))
            ->method('exists')
            ->with('/tmp/test/TestService')
            ->willReturn(false);

        $this->successfulDownloadVersionCommonExpectations(
            $client,
            $temporaryDirectoryFactory,
            $appExtractor,
            $filesystem,
            'TestService',
            'https://example.com/service.zip',
            ['data']
        );

        $source = new ServiceSourceResolver($client, $temporaryDirectoryFactory, $appExtractor, $filesystem, new NullLogger());

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestService');
        $app->setSourceType('service');
        $app->setSourceConfig([
            'version' => '1.0.0',
            'hash' => 'abc123',
            'revision' => '1.0.0-abc123',
            'zip-url' => 'https://example.com/service.zip',
            'hash-algorithm' => 'sha256',
            'min-shop-supported-version' => '6.6.0.0',
            'requirements' => ['service_consent'],
        ]);

        $result = $source->filesystem($app);

        static::assertSame('/tmp/test/TestService', $result->location);
    }

    public function testFilesystemWithManifest(): void
    {
        $client = $this->createMock(Client::class);
        $temporaryDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $appExtractor = $this->createMock(AppExtractor::class);
        $filesystem = $this->createMock(Filesystem::class);

        $filesystem->expects($this->exactly(2))
            ->method('exists')
            ->with('/tmp/test/ManifestApp')
            ->willReturn(false);

        $manifest = $this->createMock(Manifest::class);
        $metadata = Metadata::fromArray([
            'name' => 'ManifestApp',
            'label' => [],
            'author' => 'Shopware',
            'copyright' => 'Shopware',
            'license' => 'Shopware',
            'version' => '1.0',
        ]);

        $manifest->expects($this->once())
            ->method('getMetadata')
            ->willReturn($metadata);

        $manifest->expects($this->once())
            ->method('getSourceConfig')
            ->willReturn([
                'version' => '2.0.0',
                'hash' => 'def456',
                'revision' => '2.0.0-def456',
                'zip-url' => 'https://example.com/manifest.zip',
                'hash-algorithm' => 'sha512',
                'min-shop-supported-version' => '6.7.0.0',
                'requirements' => ['service_consent'],
            ]);

        $this->successfulDownloadVersionCommonExpectations(
            $client,
            $temporaryDirectoryFactory,
            $appExtractor,
            $filesystem,
            'ManifestApp',
            'https://example.com/manifest.zip',
            ['manifest-data']
        );

        $source = new ServiceSourceResolver($client, $temporaryDirectoryFactory, $appExtractor, $filesystem, new NullLogger());

        $result = $source->filesystem($manifest);

        static::assertSame('/tmp/test/ManifestApp', $result->location);
    }

    public function testDownloadVersionThrowsExceptionOnServiceError(): void
    {
        $client = $this->createMock(Client::class);
        $temporaryDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $appExtractor = static::createStub(AppExtractor::class);
        $filesystem = $this->createMock(Filesystem::class);

        $temporaryDirectoryFactory->method('path')
            ->willReturn('/tmp/test');

        $filesystem->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $filesystem->expects($this->never())
            ->method('mkdir');

        $client->expects($this->once())
            ->method('fetchServiceZip')
            ->willThrowException(ServiceException::missingAppVersionInformation('version'));

        $filesystem->expects($this->once())
            ->method('remove')
            ->with(static::stringStartsWith('/tmp/test/FailingService.tmp-'));

        $source = new ServiceSourceResolver($client, $temporaryDirectoryFactory, $appExtractor, $filesystem, new NullLogger());

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('FailingService');
        $app->setSourceType('service');
        $app->setSourceConfig([
            'version' => '1.0.0',
            'hash' => 'abc123',
            'revision' => '1.0.0-abc123',
            'zip-url' => 'https://example.com/failing.zip',
            'hash-algorithm' => 'sha256',
            'min-shop-supported-version' => '6.6.0.0',
            'requirements' => ['service_consent'],
        ]);

        $this->expectExceptionObject(AppException::cannotMountAppFilesystem('FailingService', ServiceException::cannotWriteAppToDestination('/tmp/test/FailingService', ServiceException::missingAppVersionInformation('version'))));

        $source->filesystem($app);
    }

    public function testDownloadVersionThrowsExceptionOnExtractorError(): void
    {
        $client = $this->createMock(Client::class);
        $temporaryDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $appExtractor = $this->createMock(AppExtractor::class);
        $filesystem = $this->createMock(Filesystem::class);

        $temporaryDirectoryFactory->method('path')
            ->willReturn('/tmp/test');

        $filesystem->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $filesystem->expects($this->once())
            ->method('mkdir');

        $chunks = $this->createChunkGenerator(['data']);
        $client->expects($this->once())
            ->method('fetchServiceZip')
            ->willReturn($chunks);

        $filesystem->expects($this->once())
            ->method('appendToFile');

        $appExtractor->expects($this->once())
            ->method('extract')
            ->willThrowException(new AppArchiveValidationFailure(400, 'INVALID_ARCHIVE', 'Invalid archive'));

        $filesystem->expects($this->once())
            ->method('remove')
            ->with(static::stringStartsWith('/tmp/test/FailingExtraction.tmp-'));

        $source = new ServiceSourceResolver($client, $temporaryDirectoryFactory, $appExtractor, $filesystem, new NullLogger());

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('FailingExtraction');
        $app->setSourceType('service');
        $app->setSourceConfig([
            'version' => '1.0.0',
            'hash' => 'abc123',
            'revision' => '1.0.0-abc123',
            'zip-url' => 'https://example.com/failing.zip',
            'hash-algorithm' => 'sha256',
            'min-shop-supported-version' => '6.6.0.0',
            'requirements' => ['service_consent'],
        ]);

        $this->expectExceptionObject(AppException::cannotMountAppFilesystem('FailingExtraction', new AppArchiveValidationFailure(400, 'INVALID', 'Invalid archive')));

        $source->filesystem($app);
    }

    public function testDownloadVersionThrowsExceptionOnFileWriteError(): void
    {
        $this->io->dumpFile($this->path('TestService/manifest.xml'), 'old');
        $this->expectDownload();
        $underlying = new IOException('Write failed');
        $io = $this->createPartialMock(Filesystem::class, ['appendToFile']);
        $io->method('appendToFile')->willThrowException($underlying);

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

    public function testPreviousRevisionSurvivesAFileWriteError(): void
    {
        $this->io->dumpFile($this->path('TestService/manifest.xml'), 'old');
        $this->expectDownload();
        $underlying = new IOException('Write failed');
        $io = $this->createPartialMock(Filesystem::class, ['appendToFile']);
        $io->method('appendToFile')->willThrowException($underlying);

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

    public function testPreviousRevisionIsRestoredWhenPromotionFails(): void
    {
        $this->io->dumpFile($this->path('TestService/manifest.xml'), 'old');
        $this->expectDownload();
        $underlying = new IOException('Promotion failed');
        $io = $this->createPartialMock(Filesystem::class, ['rename']);
        $io->method('rename')->willReturnCallback(function (string $origin, string $target) use ($underlying): void {
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
        $io->method('remove')->willReturnCallback(function (string $path) use ($suffix, $underlying): void {
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
        $io->method('remove')->willThrowException($cleanupError);
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
     * Sets up common expectations for successful download scenarios
     *
     * @param string[] $chunks
     */
    private function successfulDownloadVersionCommonExpectations(
        MockObject $client,
        TemporaryDirectoryFactory&Stub $temporaryDirectoryFactory,
        MockObject $appExtractor,
        MockObject $filesystem,
        string $appName,
        string $zipUrl,
        array $chunks
    ): void {
        $staging = static::matchesRegularExpression(\sprintf('#^/tmp/test/%s\\.tmp-[^/]+$#', $appName));
        $zipPath = static::matchesRegularExpression(\sprintf('#^/tmp/test/%s\\.tmp-[^/]+/%s\\.zip$#', $appName, $appName));

        $temporaryDirectoryFactory->method('path')
            ->willReturn('/tmp/test');

        $filesystem->expects($this->once())
            ->method('mkdir')
            ->with($staging);

        $chunkGenerator = $this->createChunkGenerator($chunks);
        $client->expects($this->once())
            ->method('fetchServiceZip')
            ->with($zipUrl)
            ->willReturn($chunkGenerator);

        $filesystem->expects($this->exactly(\count($chunks)))
            ->method('appendToFile')
            ->with($zipPath, static::anything());

        $appExtractor->expects($this->once())
            ->method('extract')
            ->with(
                $zipPath,
                $staging,
                $appName
            )
            ->willReturnCallback(static fn (string $zip, string $directory): string => Path::join($directory, $appName));

        $filesystem->expects($this->once())
            ->method('remove')
            ->with($staging);
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
