<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppDownloader;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Exception\AppDownloadException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Source\RemoteZip;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(RemoteZip::class)]
class RemoteZipTest extends TestCase
{
    public function testName(): void
    {
        $source = new RemoteZip(
            new TemporaryDirectoryFactory(),
            $this->createMock(AppDownloader::class),
            $this->createMock(AppExtractor::class),
        );
        static::assertEquals('remote-zip', $source->name());
    }

    public function testSupportsExistingAppWithRemoteZipType(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setSourceType('remote-zip');

        $source = new RemoteZip(
            new TemporaryDirectoryFactory(),
            $this->createMock(AppDownloader::class),
            $this->createMock(AppExtractor::class),
        );

        static::assertTrue($source->supports($app));
    }

    public function testSupportsManifestWillHttpUrl(): void
    {
        $manifest = static::createMock(Manifest::class);
        $manifest->method('getPath')->willReturn('https://myapp.com/zip');

        $source = new RemoteZip(
            new TemporaryDirectoryFactory(),
            $this->createMock(AppDownloader::class),
            $this->createMock(AppExtractor::class),
        );

        static::assertTrue($source->supports($manifest));
    }

    public function testDoesNotSupportExistingAppWithNonLocalType(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setSourceType('local');

        $source = new RemoteZip(
            new TemporaryDirectoryFactory(),
            $this->createMock(AppDownloader::class),
            $this->createMock(AppExtractor::class),
        );

        static::assertFalse($source->supports($app));
    }

    public function testAppOnFilesystemIsUsedIfItExists(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setSourceType('local');

        $dirFactory = new TemporaryDirectoryFactory();
        $fs = $this->createMock(Filesystem::class);

        $downloader = $this->createMock(AppDownloader::class);
        $extractor = $this->createMock(AppExtractor::class);

        $downloader->expects(static::never())->method('download');
        $extractor->expects(static::never())->method('extract');

        $fs->expects(static::once())
            ->method('exists')
            ->with($dirFactory->path() . '/TestApp')
            ->willReturn(true);
        $source = new RemoteZip(
            $dirFactory,
            $downloader,
            $extractor,
            $fs
        );

        $filesystem = $source->filesystem($app);

        static::assertEquals($dirFactory->path() . '/TestApp', $filesystem->location);
    }

    public static function appProvider(): \Generator
    {
        $appFactory = static function (): AppEntity {
            $app = new AppEntity();
            $app->setId(Uuid::randomHex());
            $app->setName('TestApp');
            $app->setPath('https://myapp.com/zip');

            return $app;
        };

        yield 'app' => [$appFactory];

        $appFactory = static function (TestCase $testCase): Manifest {
            $manifest = $testCase->createMock(Manifest::class);

            $metadata = Metadata::fromArray([
                'name' => 'TestApp',
                'label' => [],
                'author' => 'Shopware',
                'copyright' => 'Shopware',
                'license' => 'Shopware',
                'version' => '1.0',
            ]);

            $manifest->method('getMetadata')->willReturn($metadata);
            $manifest->method('getPath')->willReturn('https://myapp.com/zip');

            return $manifest;
        };

        yield 'manifest' => [$appFactory];
    }

    /**
     * @param callable(TestCase):(AppEntity|Manifest) $appFactory
     */
    #[DataProvider('appProvider')]
    public function testAppIsDownloadedIfItDoesNotExistOnFilesystem(callable $appFactory): void
    {
        $app = $appFactory($this);

        $dirFactory = new TemporaryDirectoryFactory();
        $fs = $this->createMock(Filesystem::class);

        $downloader = $this->createMock(AppDownloader::class);
        $extractor = $this->createMock(AppExtractor::class);

        $downloader
            ->expects(static::once())
            ->method('download')
            ->with('https://myapp.com/zip', $dirFactory->path() . '/TestApp.zip');

        $extractor
            ->expects(static::once())
            ->method('extract')
            ->with(
                'TestApp',
                $dirFactory->path() . '/TestApp.zip',
                $dirFactory->path() . '/TestApp'
            );

        $fs->method('exists')
            ->with($dirFactory->path() . '/TestApp')
            ->willReturn(false);

        $source = new RemoteZip(
            $dirFactory,
            $downloader,
            $extractor,
            $fs
        );

        $filesystem = $source->filesystem($app);

        static::assertEquals($dirFactory->path() . '/TestApp', $filesystem->location);
    }

    public function testExceptionIsThrowIfDownloadingOrExtractingFails(): void
    {
        $exception = AppDownloadException::transportError('https://myapp.com/zip');
        $this->expectExceptionObject(AppException::cannotMountAppFilesystem('TestApp', $exception));

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setPath('https://myapp.com/zip');

        $dirFactory = new TemporaryDirectoryFactory();
        $fs = $this->createMock(Filesystem::class);

        $downloader = $this->createMock(AppDownloader::class);
        $extractor = $this->createMock(AppExtractor::class);

        $downloader
            ->expects(static::once())
            ->method('download')
            ->with('https://myapp.com/zip', $dirFactory->path() . '/TestApp.zip')
            ->willThrowException($exception);

        $source = new RemoteZip(
            $dirFactory,
            $downloader,
            $extractor,
            $fs
        );

        $source->filesystem($app);
    }
}
