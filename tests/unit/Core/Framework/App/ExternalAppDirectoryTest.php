<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Event\AppUploadValidationEvent;
use Shopware\Core\Framework\App\ExternalAppDirectory;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\ExternalStorage;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExternalAppDirectory::class)]
class ExternalAppDirectoryTest extends TestCase
{
    private const APP_ZIP = __DIR__ . '/../Plugin/_fixtures/archives/App.zip';

    public function testStoreWritesHashedAppVersionAndLoadsIt(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $directory = $this->createDirectory($storage);

        $metadata = $directory->store(self::APP_ZIP);
        static::assertSame('SwagApp', $metadata->name);
        static::assertSame('1.0.0', $metadata->version);

        $hash = Hasher::hashFile(self::APP_ZIP);
        $appDirectory = ExternalAppDirectory::DIRECTORY . '/SwagApp/' . $hash;

        static::assertTrue($storage->fileExists($appDirectory . '/SwagApp.zip'));
        static::assertTrue($storage->fileExists($appDirectory . '/manifest.xml'));

        $apps = $directory->load();

        static::assertArrayHasKey('SwagApp', $apps);
        static::assertSame($appDirectory . '/SwagApp.zip', $apps['SwagApp']->getPath());
        static::assertSame(ExternalStorage::name(), $apps['SwagApp']->getSourceType());
    }

    public function testLoadSupportsLegacyLayout(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/App/TestApp/manifest.xml');
        $manifestContent = file_get_contents(__DIR__ . '/_fixtures/App/TestApp/manifest.xml');
        static::assertIsString($manifestContent);

        $storage->write(ExternalAppDirectory::DIRECTORY . '/minimal/manifest.xml', $manifestContent);
        $storage->write(ExternalAppDirectory::DIRECTORY . '/minimal/minimal.zip', 'zip');

        $apps = $this->createDirectory($storage)->load();

        static::assertArrayHasKey('minimal', $apps);
        static::assertSame(ExternalAppDirectory::DIRECTORY . '/minimal/minimal.zip', $apps['minimal']->getPath());
        static::assertSame($manifest->getMetadata()->getName(), $apps['minimal']->getMetadata()->getName());
    }

    public function testDeleteRemovesWholeAppDirectory(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $directory = $this->createDirectory($storage);
        $directory->store(self::APP_ZIP);

        $directory->delete('SwagApp');

        static::assertFalse($storage->directoryExists(ExternalAppDirectory::DIRECTORY . '/SwagApp'));
    }

    #[DataProvider('supportedSourceTypes')]
    public function testUpdatedAppRemovesVersionsTheRowNoLongerPointsTo(string $sourceType): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $directory = $this->createDirectory($storage);

        $storage->createDirectory(ExternalAppDirectory::DIRECTORY . '/SwagApp/old');
        $storage->createDirectory(ExternalAppDirectory::DIRECTORY . '/SwagApp/current');
        $storage->write(ExternalAppDirectory::DIRECTORY . '/SwagApp/current/manifest.xml', 'manifest');

        $manifest = AppFixture::createManifest('SwagApp');
        $manifest->setSourceType($sourceType);
        $manifest->setPath(ExternalAppDirectory::DIRECTORY . '/SwagApp/current/SwagApp.zip');
        $directory->removeOldVersions($manifest);

        static::assertFalse($storage->directoryExists(ExternalAppDirectory::DIRECTORY . '/SwagApp/old'));
        static::assertTrue($storage->directoryExists(ExternalAppDirectory::DIRECTORY . '/SwagApp/current'));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function supportedSourceTypes(): \Generator
    {
        yield 'external storage' => [ExternalStorage::name()];
        yield 'legacy private app' => [ExternalStorage::LEGACY_SOURCE_TYPE];
        yield 'previous flysystem identifier' => [ExternalStorage::PREVIOUS_SOURCE_TYPE];
    }

    public function testRejectedUploadDoesNotWriteFiles(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $dispatcher = new EventDispatcher();
        $exception = new \RuntimeException('Security validation failed');
        $dispatcher->addListener(AppUploadValidationEvent::class, static function (AppUploadValidationEvent $event) use ($exception): void {
            static::assertSame(self::APP_ZIP, $event->zipPath);
            throw $exception;
        });

        try {
            $this->expectExceptionObject($exception);
            $this->createDirectory($storage, $dispatcher)->store(self::APP_ZIP);
        } finally {
            static::assertSame([], $storage->listContents('', true)->toArray());
        }
    }

    public function testCleanupKeepsVersionsWhenCurrentPathCannotBeIdentified(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $storage->createDirectory('private-apps/SwagApp/old');
        $manifest = AppFixture::createManifest('SwagApp');
        $manifest->setSourceType(ExternalStorage::name());
        $manifest->setPath('private-apps/SwagApp/SwagApp.zip');

        $this->createDirectory($storage)->removeOldVersions($manifest);

        static::assertTrue($storage->directoryExists('private-apps/SwagApp/old'));
    }

    private function createDirectory(Filesystem $storage, ?EventDispatcherInterface $dispatcher = null): ExternalAppDirectory
    {
        return new ExternalAppDirectory(
            $storage,
            new AppExtractor(new AppArchiveValidator()),
            new TemporaryDirectoryFactory(),
            new NullLogger(),
            $dispatcher ?? static::createStub(EventDispatcherInterface::class),
            new Io(),
        );
    }
}
