<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Source;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppDownloader;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\FlysystemAppDirectory;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\FlysystemSource;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FlysystemSource::class)]
class FlysystemSourceTest extends TestCase
{
    public function testNameAndSupportedSourceTypes(): void
    {
        $source = $this->createSource(static::createStub(FilesystemOperator::class));

        static::assertSame(FlysystemAppDirectory::SOURCE_TYPE, $source->name());

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());

        static::assertFalse($source->supports($app));

        $app->setSourceType(FlysystemAppDirectory::SOURCE_TYPE);
        static::assertTrue($source->supports($app));

        $app->setSourceType(FlysystemAppDirectory::LEGACY_SOURCE_TYPE);
        static::assertTrue($source->supports($app));

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getSourceType')->willReturn(FlysystemAppDirectory::SOURCE_TYPE);
        static::assertTrue($source->supports($manifest));
    }

    public function testFilesystemDownloadsAndExtractsApp(): void
    {
        $storage = static::createStub(FilesystemOperator::class);
        $temporaryDirectoryFactory = new TemporaryDirectoryFactory();
        $downloader = $this->createMock(AppDownloader::class);
        $extractor = $this->createMock(AppExtractor::class);

        $path = 'private-apps/TestApp/hash/TestApp.zip';
        $expectedDirectory = Path::join($temporaryDirectoryFactory->path(), Hasher::hash($path), '1.0.0');
        $localZipPath = Path::join($expectedDirectory, 'TestApp.zip');

        $downloader->expects($this->once())
            ->method('downloadFromFilesystem')
            ->with($storage, $path, $localZipPath);
        $extractor->expects($this->once())
            ->method('extract')
            ->with($localZipPath, $expectedDirectory, 'TestApp');

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setVersion('1.0.0');
        $app->setPath($path);
        $app->setSourceType(FlysystemAppDirectory::SOURCE_TYPE);

        $filesystem = $this->createSource($storage, $downloader, $extractor)->filesystem($app);

        static::assertSame(Path::join($expectedDirectory, 'TestApp'), $filesystem->location);
    }

    public function testResetRemovesTemporaryFilesystems(): void
    {
        $io = $this->createMock(Filesystem::class);
        $io->expects($this->once())->method('remove')->with(['/app-root']);

        $source = new FlysystemSource(
            static::createStub(FilesystemOperator::class),
            new TemporaryDirectoryFactory(),
            $io,
            static::createStub(AppDownloader::class),
            static::createStub(AppExtractor::class),
            new NullLogger(),
        );

        $source->reset([new StaticFilesystem()]);
    }

    private function createSource(
        FilesystemOperator $storage,
        ?AppDownloader $downloader = null,
        ?AppExtractor $extractor = null,
    ): FlysystemSource {
        return new FlysystemSource(
            $storage,
            new TemporaryDirectoryFactory(),
            new Filesystem(),
            $downloader ?? static::createStub(AppDownloader::class),
            $extractor ?? static::createStub(AppExtractor::class),
            new NullLogger(),
        );
    }
}
