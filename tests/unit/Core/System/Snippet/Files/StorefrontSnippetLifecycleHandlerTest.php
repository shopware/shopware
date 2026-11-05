<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem as AppFilesystem;
use Shopware\Core\System\Snippet\Files\StorefrontSnippetLifecycleHandler;
use Shopware\Core\System\Snippet\Files\StorefrontSnippetStorage;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontSnippetLifecycleHandler::class)]
final class StorefrontSnippetLifecycleHandlerTest extends TestCase
{
    private StorefrontSnippetStorage $storage;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->storage = new StorefrontSnippetStorage($this->filesystem, static::createStub(SourceResolver::class), new NullLogger(), __DIR__);
    }

    public function testInstallSnapshotsSnippetFilesAndInvalidatesTranslatorCacheForEverySnippetSet(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['aaaa', 'bbbb']);

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(['translator-aaaa', 'translator-bbbb'], true);

        $handler = new StorefrontSnippetLifecycleHandler($this->storage, $cacheInvalidator, $connection);

        $handler->install($this->buildPersistContext('AppWithStorefrontSnippets'));

        static::assertTrue($this->filesystem->fileExists('translation/apps/TestApp.json'));
    }

    public function testUpdateWithUnchangedSnippetsDoesNotInvalidateCache(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $installHandler = new StorefrontSnippetLifecycleHandler($this->storage, static::createStub(CacheInvalidator::class), $connection);
        $installHandler->install($this->buildPersistContext('AppWithStorefrontSnippets'));

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->never())->method('invalidate');

        $updateHandler = new StorefrontSnippetLifecycleHandler($this->storage, $cacheInvalidator, $connection);
        $updateHandler->update($this->buildPersistContext('AppWithStorefrontSnippets'));
    }

    public function testUpdateThatChangesSnippetsInvalidatesCache(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $installHandler = new StorefrontSnippetLifecycleHandler($this->storage, static::createStub(CacheInvalidator::class), $connection);
        $installHandler->install($this->buildPersistContext('AppWithStorefrontSnippets'));

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidate');

        $updateHandler = new StorefrontSnippetLifecycleHandler($this->storage, $cacheInvalidator, $connection);
        $updateHandler->update($this->buildPersistContext('AppWithNestedSnippets', '2.0.0'));
    }

    public function testInstallWithoutSnippetsDoesNotInvalidateCache(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->never())->method('invalidate');

        $handler = new StorefrontSnippetLifecycleHandler($this->storage, $cacheInvalidator, $connection);

        $handler->install($this->buildPersistContext('AppWithoutSnippets'));
    }

    public function testUpdateThatRemovesAllSnippetsInvalidatesCache(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $installHandler = new StorefrontSnippetLifecycleHandler($this->storage, static::createStub(CacheInvalidator::class), $connection);
        $installHandler->install($this->buildPersistContext('AppWithStorefrontSnippets'));

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidate');

        $updateHandler = new StorefrontSnippetLifecycleHandler($this->storage, $cacheInvalidator, $connection);
        $updateHandler->update($this->buildPersistContext('AppWithoutSnippets', '2.0.0'));

        static::assertJsonStringEqualsJsonString(
            '{"version":"2.0.0","files":[]}',
            $this->filesystem->read('translation/apps/TestApp.json')
        );
    }

    public function testUninstallRemovesStoredFilesAndInvalidatesCache(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $installHandler = new StorefrontSnippetLifecycleHandler($this->storage, static::createStub(CacheInvalidator::class), $connection);
        $installHandler->install($this->buildPersistContext('AppWithStorefrontSnippets'));

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidate');

        $uninstallHandler = new StorefrontSnippetLifecycleHandler($this->storage, $cacheInvalidator, $connection);
        $uninstallHandler->uninstall(new AppRemovalContext($this->buildApp(), Context::createDefaultContext()));

        static::assertFalse($this->filesystem->fileExists('translation/apps/TestApp.json'));
    }

    public function testUninstallWithoutStoredFilesDoesNotInvalidateCache(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->never())->method('invalidate');

        $handler = new StorefrontSnippetLifecycleHandler($this->storage, $cacheInvalidator, static::createStub(Connection::class));

        $handler->uninstall(new AppRemovalContext($this->buildApp(), Context::createDefaultContext()));
    }

    private function buildPersistContext(string $fixtureDir, string $version = '1.0.0'): AppPersistContext
    {
        return new AppPersistContext(
            manifest: static::createStub(Manifest::class),
            app: $this->buildApp($version),
            context: Context::createDefaultContext(),
            appFilesystem: new AppFilesystem(__DIR__ . '/_fixtures/' . $fixtureDir),
            defaultLocale: 'en-GB',
        );
    }

    private function buildApp(string $version = '1.0.0'): AppEntity
    {
        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setVersion($version);

        return $app;
    }
}
