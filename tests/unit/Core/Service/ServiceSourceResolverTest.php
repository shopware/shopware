<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\AppInfo;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceSourceResolver;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceSourceResolver::class)]
class ServiceSourceResolverTest extends TestCase
{
    private const MANIFEST = 'manifest.xml';

    private const CUSTOM_FIELDS = 'Resources/config/custom-fields.xml';

    private Io $io;

    private string $root;

    protected function setUp(): void
    {
        $this->io = new Io();
        $this->root = Path::join((string) realpath(sys_get_temp_dir()), Uuid::randomHex());
    }

    protected function tearDown(): void
    {
        $this->io->remove($this->root);
    }

    public function testName(): void
    {
        static::assertSame('service', $this->resolver(static::createStub(Client::class))->name());
    }

    public function testSupportsOnlyConsidersServiceTypes(): void
    {
        $source = $this->resolver(static::createStub(Client::class));

        $app = $this->serviceApp();

        static::assertTrue($source->supports($app));

        $app->setSourceType('not-supported');

        static::assertFalse($source->supports($app));
    }

    public function testSupportSelfManagedManifestsWithHttpUrls(): void
    {
        $metadata = $this->metadata('TestApp');
        $metadata->setSelfManaged(true);

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getPath')->willReturn('https://example.com');
        $manifest->method('getMetadata')->willReturn($metadata);

        static::assertTrue($this->resolver(static::createStub(Client::class))->supports($manifest));
    }

    public function testFilesystemForVersion(): void
    {
        $filesystem = $this->download('TestService', [self::MANIFEST => '<manifest/>']);

        static::assertSame($this->path('TestService'), $filesystem->location);
        static::assertFileExists($this->path('TestService', self::MANIFEST));
    }

    public function testFilesystemWhenAppExists(): void
    {
        $this->io->mkdir($this->path('TestService'));

        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('fetchServiceZip');

        $filesystem = $this->resolver($client)->filesystem($this->serviceApp());

        static::assertSame($this->path('TestService'), $filesystem->location);
    }

    public function testAppIsDownloadedIfItDoesNotExistOnFilesystem(): void
    {
        $client = $this->clientServing('TestService', [self::MANIFEST => '<manifest/>']);

        $app = $this->serviceApp();
        $app->setSourceConfig($this->sourceConfig());

        $filesystem = $this->resolver($client)->filesystem($app);

        static::assertSame($this->path('TestService'), $filesystem->location);
        static::assertFileExists($this->path('TestService', self::MANIFEST));
    }

    public function testFilesystemWithManifest(): void
    {
        $client = $this->clientServing('ManifestApp', [self::MANIFEST => '<manifest/>']);

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($this->metadata('ManifestApp'));
        $manifest->method('getSourceConfig')->willReturn($this->sourceConfig());

        $filesystem = $this->resolver($client)->filesystem($manifest);

        static::assertSame($this->path('ManifestApp'), $filesystem->location);
        static::assertFileExists($this->path('ManifestApp', self::MANIFEST));
    }

    public function testDownloadVersionThrowsExceptionOnServiceError(): void
    {
        $underlying = ServiceException::missingAppVersionInformation('version');

        $client = static::createStub(Client::class);
        $client->method('fetchServiceZip')->willThrowException($underlying);

        $this->expectExceptionObject(AppException::cannotMountAppFilesystem(
            'FailingService',
            ServiceException::cannotWriteAppToDestination($this->path('FailingService'), $underlying)
        ));

        $this->resolver($client)->filesystemForVersion($this->appInfo('FailingService'));
    }

    public function testDownloadVersionThrowsExceptionOnExtractorError(): void
    {
        $client = static::createStub(Client::class);
        $client->method('fetchServiceZip')->willReturn($this->chunks('not-a-zip-archive'));

        $this->expectException(AppException::class);

        $this->resolver($client)->filesystemForVersion($this->appInfo('FailingExtraction'));
    }

    public function testDownloadVersionThrowsExceptionOnFileWriteError(): void
    {
        $underlying = new \Exception('Write failed');

        $client = static::createStub(Client::class);
        $client->method('fetchServiceZip')->willReturn($this->chunks('irrelevant'));

        $io = static::createStub(Io::class);
        $io->method('appendToFile')->willThrowException($underlying);

        $this->expectExceptionObject(AppException::cannotMountAppFilesystem(
            'WriteFailService',
            ServiceException::cannotWriteAppToDestination($this->path('WriteFailService'), $underlying)
        ));

        $this->resolver($client, $io)->filesystemForVersion($this->appInfo('WriteFailService'));
    }

    public function testFilesFromThePreviousRevisionAreRemoved(): void
    {
        $this->download('TestService', [
            self::MANIFEST => '<manifest/>',
            self::CUSTOM_FIELDS => '<custom-fields/>',
        ]);

        static::assertFileExists($this->path('TestService', self::CUSTOM_FIELDS));

        $this->download('TestService', [self::MANIFEST => '<manifest/>']);

        static::assertFileExists($this->path('TestService', self::MANIFEST));
        static::assertFileDoesNotExist($this->path('TestService', self::CUSTOM_FIELDS));
        static::assertSame(['TestService'], $this->entriesInRoot());
    }

    public function testPreviousRevisionSurvivesAFailedDownload(): void
    {
        $this->download('TestService', [self::MANIFEST => '<manifest/>']);

        $client = static::createStub(Client::class);
        $client->method('fetchServiceZip')
            ->willThrowException(ServiceException::missingAppVersionInformation('version'));

        try {
            $this->resolver($client)->filesystemForVersion($this->appInfo('TestService'));
            static::fail(AppException::class . ' should have been thrown');
        } catch (AppException) {
        }

        static::assertFileExists($this->path('TestService', self::MANIFEST));
        static::assertSame(['TestService'], $this->entriesInRoot());
    }

    /**
     * @param array<string, string> $files
     */
    private function download(string $serviceName, array $files): Filesystem
    {
        return $this->resolver($this->clientServing($serviceName, $files))
            ->filesystemForVersion($this->appInfo($serviceName));
    }

    private function resolver(Client $client, ?Io $io = null): ServiceSourceResolver
    {
        $temporaryDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $temporaryDirectoryFactory->method('path')->willReturn($this->root);

        return new ServiceSourceResolver(
            $client,
            $temporaryDirectoryFactory,
            new AppExtractor(new AppArchiveValidator()),
            $io ?? $this->io
        );
    }

    /**
     * @param array<string, string> $files
     */
    private function clientServing(string $serviceName, array $files): Client
    {
        $client = static::createStub(Client::class);
        $client->method('fetchServiceZip')->willReturn($this->chunks($this->zip($serviceName, $files)));

        return $client;
    }

    /**
     * @param array<string, string> $files
     */
    private function zip(string $serviceName, array $files): string
    {
        $path = $this->root . '-' . Uuid::randomHex() . '.zip';

        $archive = new \ZipArchive();
        $archive->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $archive->addFromString(Path::join($serviceName, $name), $contents);
        }

        $archive->close();

        $zip = (string) file_get_contents($path);
        $this->io->remove($path);

        return $zip;
    }

    /**
     * @return \Generator<ChunkInterface>
     */
    private function chunks(string $data): \Generator
    {
        foreach (str_split($data, 64) as $part) {
            $chunk = static::createStub(ChunkInterface::class);
            $chunk->method('getContent')->willReturn($part);

            yield $chunk;
        }
    }

    private function path(string ...$parts): string
    {
        return Path::join($this->root, ...$parts);
    }

    /**
     * @return list<string>
     */
    private function entriesInRoot(): array
    {
        $entries = scandir($this->root);
        \assert($entries !== false);

        return array_values(array_diff($entries, ['.', '..']));
    }

    private function serviceApp(): AppEntity
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestService');
        $app->setSourceType('service');

        return $app;
    }

    private function appInfo(string $serviceName): AppInfo
    {
        return AppInfo::fromNameAndSourceConfig($serviceName, $this->sourceConfig());
    }

    /**
     * @return array{version: string, hash: string, revision: string, zip-url: string, hash-algorithm: string, min-shop-supported-version: string, requirements: non-empty-list<string>}
     */
    private function sourceConfig(): array
    {
        return [
            'version' => '1.0.0',
            'hash' => 'abc123',
            'revision' => '1.0.0-abc123',
            'zip-url' => 'https://example.com/service.zip',
            'hash-algorithm' => 'sha256',
            'min-shop-supported-version' => '6.6.0.0',
            'requirements' => ['service_consent'],
        ];
    }

    private function metadata(string $name): Metadata
    {
        return Metadata::fromArray([
            'name' => $name,
            'label' => [],
            'author' => 'Shopware',
            'copyright' => 'Shopware',
            'license' => 'Shopware',
            'version' => '1.0',
        ]);
    }
}
