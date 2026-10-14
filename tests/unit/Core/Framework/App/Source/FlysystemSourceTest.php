<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Source;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppDownloader;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Event\PostAppDeletedEvent;
use Shopware\Core\Framework\App\Exception\AppDownloadException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Source\FlysystemSource;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[CoversClass(FlysystemSource::class)]
class FlysystemSourceTest extends TestCase
{
    public function testNameAndSourceType(): void
    {
        $source = $this->source(storagePrefix: 'private-apps', sourceType: 'private-app');

        static::assertSame('flysystem', FlysystemSource::name());
        static::assertSame('private-app', $source->sourceType());
        static::assertSame([PostAppDeletedEvent::class => 'onAppDeleted'], FlysystemSource::getSubscribedEvents());
    }

    public function testSupportsOnlyAppsOfTheConfiguredSourceType(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setSourceType('private-app');

        $source = $this->source(sourceType: 'private-app');

        static::assertTrue($source->supports($app));

        $app->setSourceType('store');

        static::assertFalse($source->supports($app));
    }

    public function testDeletesOnlyAppsOwnedByConfiguredSourceType(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())->method('deleteDirectory')->with('private-apps/ExampleApp');

        $source = $this->source($storage, sourceType: 'private-app', storagePrefix: 'private-apps');

        $source->onAppDeleted(new PostAppDeletedEvent('ExampleApp', 'private-app', Context::createDefaultContext()));
        $source->onAppDeleted(new PostAppDeletedEvent('StoreApp', 'store', Context::createDefaultContext()));
    }

    public function testFilesystemDownloadsAndExtractsAppArchive(): void
    {
        $storage = static::createStub(FilesystemOperator::class);
        $downloader = $this->createMock(AppDownloader::class);
        $extractor = $this->createMock(AppExtractor::class);
        $io = $this->createMock(Io::class);

        $zipPath = 'apps/TestApp/TestApp.zip';
        $temporaryPath = Path::join('/tmp/flysystem', Hasher::hash($zipPath), '1.0.0');
        $localZipPath = Path::join($temporaryPath, 'TestApp.zip');

        $downloader->expects($this->once())
            ->method('downloadFromFilesystem')
            ->with($storage, $zipPath, $localZipPath);
        $extractor->expects($this->once())
            ->method('extract')
            ->with($localZipPath, $temporaryPath, 'TestApp');
        $io->expects($this->once())->method('remove')->with($localZipPath);

        $source = $this->source($storage, downloader: $downloader, extractor: $extractor, io: $io);

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setVersion('1.0.0');
        $app->setPath($zipPath);

        static::assertSame(Path::join($temporaryPath, 'TestApp'), $source->filesystem($app)->location);
    }

    public function testFilesystemWrapsDownloadFailure(): void
    {
        $storage = static::createStub(FilesystemOperator::class);
        $downloader = $this->createMock(AppDownloader::class);
        $exception = AppDownloadException::transportError('apps/TestApp/TestApp.zip');
        $downloader->expects($this->once())
            ->method('downloadFromFilesystem')
            ->willThrowException($exception);

        $this->expectExceptionObject(AppException::cannotMountAppFilesystem('TestApp', $exception));

        $source = $this->source($storage, downloader: $downloader);

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setVersion('1.0.0');
        $app->setPath('apps/TestApp/TestApp.zip');

        $source->filesystem($app);
    }

    public function testResetRemovesEveryFilesystemLocation(): void
    {
        $io = $this->createMock(Io::class);
        $io->expects($this->once())->method('remove')->with(['/tmp/one', '/tmp/two']);

        $source = $this->source(io: $io);
        $source->reset([new Filesystem('/tmp/one'), new Filesystem('/tmp/two')]);
    }

    public function testStoreArchiveWritesManifestAndZipToStorage(): void
    {
        $directory = sys_get_temp_dir() . '/flysystem-store-' . bin2hex(random_bytes(5));
        $io = new Io();
        $io->mkdir($directory);
        $io->dumpFile($directory . '/manifest.xml', '<manifest/>');
        $archivePath = $directory . '/archive.zip';
        $io->dumpFile($archivePath, 'zip-content');

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())->method('createDirectory')->with('private-apps/TestApp');

        $written = [];
        $storage->expects($this->exactly(2))
            ->method('writeStream')
            ->willReturnCallback(static function (string $location, $stream) use (&$written): void {
                \assert(\is_resource($stream));
                $written[] = $location;
            });

        try {
            $source = $this->source($storage, storagePrefix: 'private-apps');
            $manifest = $this->manifest('TestApp', $directory);

            static::assertSame('private-apps/TestApp/TestApp.zip', $source->storeArchive($archivePath, $manifest));
            static::assertSame(['private-apps/TestApp/manifest.xml', 'private-apps/TestApp/TestApp.zip'], $written);
        } finally {
            $io->remove($directory);
        }
    }

    public function testBackupReadsManifestAndZipFromStorage(): void
    {
        $directory = sys_get_temp_dir() . '/flysystem-backup-' . bin2hex(random_bytes(5));
        $io = new Io();
        $io->mkdir($directory);

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->exactly(2))
            ->method('readStream')
            ->willReturnCallback(static fn (): mixed => fopen('php://temp', 'r+'));

        try {
            $source = $this->source($storage, io: $io, temporaryDirectory: $directory);
            $app = new AppEntity();
            $app->setId(Uuid::randomHex());
            $app->setName('TestApp');

            $backupPath = $source->backup($app);

            static::assertStringStartsWith($directory . '/app-backup-', $backupPath);
            static::assertFileExists($backupPath . '/manifest.xml');
            static::assertFileExists($backupPath . '/TestApp.zip');
        } finally {
            $io->remove($directory);
        }
    }

    public function testRestoreWritesBackupFilesBackToStorage(): void
    {
        $directory = sys_get_temp_dir() . '/flysystem-restore-' . bin2hex(random_bytes(5));
        $io = new Io();
        $io->mkdir($directory);
        $io->dumpFile($directory . '/manifest.xml', 'old manifest');
        $io->dumpFile($directory . '/TestApp.zip', 'old zip');

        $storage = $this->createMock(FilesystemOperator::class);
        $written = [];
        $storage->expects($this->exactly(2))
            ->method('writeStream')
            ->willReturnCallback(static function (string $location, $stream) use (&$written): void {
                \assert(\is_resource($stream));
                $written[] = $location;
            });

        try {
            $source = $this->source($storage, storagePrefix: 'private-apps');
            $app = new AppEntity();
            $app->setId(Uuid::randomHex());
            $app->setName('TestApp');

            $source->restore($app, $directory);

            static::assertSame(['private-apps/TestApp/manifest.xml', 'private-apps/TestApp/TestApp.zip'], $written);
        } finally {
            $io->remove($directory);
        }
    }

    public function testRemoveDeletesTheAppDirectory(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())->method('deleteDirectory')->with('private-apps/TestApp');

        $source = $this->source($storage, storagePrefix: 'private-apps');
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');

        $source->remove($app);
    }

    public function testRemoveByManifestDeletesTheAppDirectory(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())->method('deleteDirectory')->with('apps/TestApp');

        $source = $this->source($storage);
        $source->removeByManifest($this->manifest('TestApp', '/tmp'));
    }

    private function source(
        ?FilesystemOperator $storage = null,
        ?string $sourceType = null,
        ?string $storagePrefix = null,
        ?AppDownloader $downloader = null,
        ?AppExtractor $extractor = null,
        ?Io $io = null,
        ?string $temporaryDirectory = null,
    ): FlysystemSource {
        $temporaryDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $temporaryDirectoryFactory->method('path')->willReturn($temporaryDirectory ?? '/tmp/flysystem');

        return new FlysystemSource(
            $storage ?? static::createStub(FilesystemOperator::class),
            $temporaryDirectoryFactory,
            $io ?? static::createStub(Io::class),
            $downloader ?? static::createStub(AppDownloader::class),
            $extractor ?? static::createStub(AppExtractor::class),
            new NullLogger(),
            $sourceType ?? 'flysystem',
            $storagePrefix ?? 'apps',
        );
    }

    private function manifest(string $name, string $path): Manifest
    {
        $metadata = Metadata::fromArray([
            'name' => $name,
            'label' => [],
            'author' => 'Shopware',
            'copyright' => 'Shopware',
            'license' => 'Shopware',
            'version' => '1.0.0',
        ]);

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);
        $manifest->method('getPath')->willReturn($path);

        return $manifest;
    }
}
