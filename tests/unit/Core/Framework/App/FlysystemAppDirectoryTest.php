<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Event\PostAppDeletedEvent;
use Shopware\Core\Framework\App\FlysystemAppDirectory;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem as Io;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FlysystemAppDirectory::class)]
class FlysystemAppDirectoryTest extends TestCase
{
    private const APP_ZIP = __DIR__ . '/../Plugin/_fixtures/archives/App.zip';

    public function testStoreWritesHashedAppVersionAndLoadsIt(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $directory = $this->createDirectory($storage);

        $directory->store(self::APP_ZIP);

        $hash = Hasher::hashFile(self::APP_ZIP);
        $appDirectory = FlysystemAppDirectory::DIRECTORY . '/SwagApp/' . $hash;

        static::assertTrue($storage->fileExists($appDirectory . '/SwagApp.zip'));
        static::assertTrue($storage->fileExists($appDirectory . '/manifest.xml'));

        $apps = $directory->load();

        static::assertArrayHasKey('SwagApp', $apps);
        static::assertSame($appDirectory . '/SwagApp.zip', $apps['SwagApp']->getPath());
        static::assertSame(FlysystemAppDirectory::SOURCE_TYPE, $apps['SwagApp']->getSourceType());
    }

    public function testLoadSupportsLegacyLayout(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/App/TestApp/manifest.xml');
        $manifestContent = file_get_contents(__DIR__ . '/_fixtures/App/TestApp/manifest.xml');
        static::assertIsString($manifestContent);

        $storage->write(FlysystemAppDirectory::DIRECTORY . '/minimal/manifest.xml', $manifestContent);
        $storage->write(FlysystemAppDirectory::DIRECTORY . '/minimal/minimal.zip', 'zip');

        $apps = $this->createDirectory($storage)->load();

        static::assertArrayHasKey('minimal', $apps);
        static::assertSame(FlysystemAppDirectory::DIRECTORY . '/minimal/minimal.zip', $apps['minimal']->getPath());
        static::assertSame($manifest->getMetadata()->getName(), $apps['minimal']->getMetadata()->getName());
    }

    public function testDeleteRemovesWholeAppDirectory(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $directory = $this->createDirectory($storage);
        $directory->store(self::APP_ZIP);

        $directory->delete('SwagApp');

        static::assertFalse($storage->directoryExists(FlysystemAppDirectory::DIRECTORY . '/SwagApp'));
    }

    public function testUpdatedAppRemovesVersionsTheRowNoLongerPointsTo(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $directory = $this->createDirectory($storage);

        $storage->createDirectory(FlysystemAppDirectory::DIRECTORY . '/SwagApp/old');
        $storage->createDirectory(FlysystemAppDirectory::DIRECTORY . '/SwagApp/current');
        $storage->write(FlysystemAppDirectory::DIRECTORY . '/SwagApp/current/manifest.xml', 'manifest');

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('SwagApp');
        $app->setSourceType(FlysystemAppDirectory::SOURCE_TYPE);
        $app->setPath(FlysystemAppDirectory::DIRECTORY . '/SwagApp/current/SwagApp.zip');

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/App/TestApp/manifest.xml');
        $directory->onAppUpdated(new AppUpdatedEvent($app, $manifest, Context::createDefaultContext()));

        static::assertFalse($storage->directoryExists(FlysystemAppDirectory::DIRECTORY . '/SwagApp/old'));
        static::assertTrue($storage->directoryExists(FlysystemAppDirectory::DIRECTORY . '/SwagApp/current'));
    }

    public function testDeletedAppRemovesFilesForFlysystemAndLegacySourceTypes(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $directory = $this->createDirectory($storage);
        $context = Context::createDefaultContext();

        $storage->createDirectory(FlysystemAppDirectory::DIRECTORY . '/FlysystemApp');
        $storage->createDirectory(FlysystemAppDirectory::DIRECTORY . '/LegacyApp');
        $storage->createDirectory(FlysystemAppDirectory::DIRECTORY . '/StoreApp');

        $directory->onAppDeleted(new PostAppDeletedEvent('FlysystemApp', FlysystemAppDirectory::SOURCE_TYPE, $context));
        $directory->onAppDeleted(new PostAppDeletedEvent('LegacyApp', FlysystemAppDirectory::LEGACY_SOURCE_TYPE, $context));
        $directory->onAppDeleted(new PostAppDeletedEvent('StoreApp', 'store', $context));

        static::assertFalse($storage->directoryExists(FlysystemAppDirectory::DIRECTORY . '/FlysystemApp'));
        static::assertFalse($storage->directoryExists(FlysystemAppDirectory::DIRECTORY . '/LegacyApp'));
        static::assertTrue($storage->directoryExists(FlysystemAppDirectory::DIRECTORY . '/StoreApp'));
    }

    private function createDirectory(Filesystem $storage): FlysystemAppDirectory
    {
        return new FlysystemAppDirectory(
            $storage,
            new AppExtractor(new AppArchiveValidator()),
            new TemporaryDirectoryFactory(),
            new NullLogger(),
            new Io(),
        );
    }
}
