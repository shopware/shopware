<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheClearer::class)]
class CacheClearerTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/_fixtures';

    private CacheClearer $cacheClearer;

    /**
     * @var array<string, CacheItemPoolInterface&MockObject>
     */
    private array $adapters;

    private CacheItemPoolInterface&MockObject $appAdapter;

    private CacheItemPoolInterface&MockObject $httpAdapter;

    private CacheClearerInterface&MockObject $symfonyCache;

    private AbstractReverseProxyGateway&MockObject $reverseProxyCache;

    private CacheInvalidator&MockObject $invalidator;

    private Filesystem&MockObject $filesystem;

    private MessageBusInterface&Stub $messageBus;

    private LoggerInterface&Stub $logger;

    private string $cacheDir;

    private LockFactory&Stub $lock;

    protected function setUp(): void
    {
        $this->appAdapter = $this->createMock(CacheItemPoolInterface::class);
        $this->httpAdapter = $this->createMock(CacheItemPoolInterface::class);
        $this->adapters = [
            'app' => $this->appAdapter,
            'http' => $this->httpAdapter,
        ];
        $this->symfonyCache = $this->createMock(CacheClearerInterface::class);
        $this->reverseProxyCache = $this->createMock(AbstractReverseProxyGateway::class);
        $this->invalidator = $this->createMock(CacheInvalidator::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->messageBus = static::createStub(MessageBusInterface::class);
        $this->logger = static::createStub(LoggerInterface::class);

        $lock = static::createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $this->lock = static::createStub(LockFactory::class);
        $this->lock->method('createLock')
            ->willReturn($lock);

        // Checked-in cache tree with a twig directory and the URL generator files, the Finder only reads it
        $this->cacheDir = self::FIXTURES . '/clear/test';

        $this->cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            true,
            $this->messageBus,
            $this->logger,
            $this->lock
        );
    }

    public function testClearWithHttpCache(): void
    {
        $this->appAdapter->expects($this->once())->method('clear');
        $this->httpAdapter->expects($this->once())->method('clear');

        $this->reverseProxyCache->expects($this->once())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        // Verify twig directory and URL generator files were removed
        $this->expectRemovals($this->expectedClearRemovals());

        $this->cacheClearer->clear(true);
    }

    public function testClearWithoutHttpCache(): void
    {
        $this->appAdapter->expects($this->once())->method('clear');
        $this->httpAdapter->expects($this->once())->method('clear');

        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        // Verify twig directory was removed
        $this->expectRemovals($this->expectedClearRemovals());

        $this->cacheClearer->clear(false);
    }

    public function testClearWithInvalidatorException(): void
    {
        $this->appAdapter->expects($this->once())->method('clear');
        $this->httpAdapter->expects($this->once())->method('clear');

        $exception = new \Exception('Redis not available');
        $this->invalidator->expects($this->once())
            ->method('invalidateExpired')
            ->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('critical')
            ->with('Could not clear cache: ' . $exception->getMessage());

        $this->reverseProxyCache->expects($this->once())->method('banAll');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            true,
            $this->messageBus,
            $logger,
            $this->lock
        );

        // Verify twig directory was removed despite the exception
        $this->expectRemovals($this->expectedClearRemovals());

        $cacheClearer->clear();
    }

    public function testClearWithUnwritableCacheDir(): void
    {
        $unwritableDir = '/unwritable_dir';
        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            $unwritableDir,
            'test',
            false,
            true,
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->once())->method('clear');
        $this->httpAdapter->expects($this->once())->method('clear');

        $this->reverseProxyCache->expects($this->once())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');
        $this->filesystem->expects($this->never())->method('remove');

        $this->expectException(AdapterException::class);
        $cacheClearer->clear();
    }

    public function testClearInClusterMode(): void
    {
        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            $this->cacheDir,
            'test',
            true, // cluster mode enabled
            true,
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->once())->method('clear');
        $this->httpAdapter->expects($this->once())->method('clear');

        $this->reverseProxyCache->expects($this->once())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        // In cluster mode, filesystem operations should not be performed
        $this->filesystem->expects($this->never())->method('remove');

        $cacheClearer->clear();
    }

    public function testClearWithoutReverseProxyCache(): void
    {
        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            null, // no reverse proxy cache
            $this->invalidator,
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            true,
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->once())->method('clear');
        $this->httpAdapter->expects($this->once())->method('clear');

        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        // Verify twig directory was removed
        $this->expectRemovals($this->expectedClearRemovals());

        $cacheClearer->clear();
    }

    public function testClearContainerCache(): void
    {
        // Checked-in cache tree with container files, a container directory and an unrelated file
        $cacheDir = self::FIXTURES . '/container/test';
        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            $cacheDir,
            'test',
            false,
            true,
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->never())->method('clear');
        $this->httpAdapter->expects($this->never())->method('clear');
        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->never())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');

        // Verify container files/dirs were removed and the non-container file was kept
        $this->expectRemovals([[
            $cacheDir . '/CachedContainer.php',
            $cacheDir . '/ContainerXYZ',
            $cacheDir . '/TestContainer.php',
        ]]);

        $cacheClearer->clearContainerCache();
    }

    public function testClearContainerCacheInClusterMode(): void
    {
        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            self::FIXTURES . '/container/test',
            'test',
            true, // cluster mode enabled
            true,
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->never())->method('clear');
        $this->httpAdapter->expects($this->never())->method('clear');
        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->never())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');

        // In cluster mode, files should not be deleted
        $this->filesystem->expects($this->never())->method('remove');

        $cacheClearer->clearContainerCache();
    }

    public function testScheduleCacheFolderCleanup(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(CleanupOldCacheFolders::class))
            ->willReturn(new Envelope(new \stdClass()));

        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            true,
            $messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->never())->method('clear');
        $this->httpAdapter->expects($this->never())->method('clear');
        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->never())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');
        $this->filesystem->expects($this->never())->method('remove');

        $cacheClearer->scheduleCacheFolderCleanup();
    }

    public function testDeleteItems(): void
    {
        $keys = ['key1', 'key2', 'key3'];

        $this->appAdapter->expects($this->once())
            ->method('deleteItems')
            ->with($keys);
        $this->httpAdapter->expects($this->once())
            ->method('deleteItems')
            ->with($keys);

        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->never())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');
        $this->filesystem->expects($this->never())->method('remove');

        $this->cacheClearer->deleteItems($keys);
    }

    public function testPrune(): void
    {
        /** @var PruneableInterface&CacheItemPoolInterface&MockObject $pruneableAdapter */
        $pruneableAdapter = $this->createMock(PruneableInterface::class);
        $pruneableAdapter->expects($this->once())->method('prune');

        $nonPruneableAdapter = static::createStub(CacheItemPoolInterface::class);

        $cacheClearer = new CacheClearer(
            [
                'pruneable' => $pruneableAdapter,
                'non_pruneable' => $nonPruneableAdapter,
            ],
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            true,
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->never())->method('clear');
        $this->httpAdapter->expects($this->never())->method('clear');
        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->never())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');
        $this->filesystem->expects($this->never())->method('remove');

        $cacheClearer->prune();
    }

    public function testCleanupOldContainerCacheDirectories(): void
    {
        // Checked-in cache tree whose parent holds other directories with the same environment prefix
        $parentDir = self::FIXTURES . '/old-dirs';
        $cacheDir = $parentDir . '/test';
        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            $cacheDir,
            'test',
            false,
            true,
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->never())->method('clear');
        $this->httpAdapter->expects($this->never())->method('clear');
        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->never())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');

        // Old cache directories with matching environment prefix are removed, the current one is kept
        $this->expectRemovals([[
            $parentDir . '/test_another_cache',
            $parentDir . '/test_different',
            $parentDir . '/test_old_cache_1',
        ]]);

        $cacheClearer->cleanupOldContainerCacheDirectories();
    }

    public function testCleanupOldContainerCacheDirectoriesInClusterMode(): void
    {
        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            self::FIXTURES . '/old-dirs/test',
            'test',
            true, // cluster mode enabled
            true,
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->never())->method('clear');
        $this->httpAdapter->expects($this->never())->method('clear');
        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->never())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');

        // In cluster mode, directories should not be deleted
        $this->filesystem->expects($this->never())->method('remove');

        $cacheClearer->cleanupOldContainerCacheDirectories();
    }

    public function testClearHttpCacheWithReverseProxy(): void
    {
        $this->reverseProxyCache->expects($this->once())->method('banAll');
        $this->appAdapter->expects($this->never())->method('clear');
        $this->httpAdapter->expects($this->never())->method('clear');

        $this->invalidator->expects($this->never())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');
        $this->filesystem->expects($this->never())->method('remove');

        $this->cacheClearer->clearHttpCache();
    }

    public function testClearHttpCacheWithoutReverseProxy(): void
    {
        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            null, // no reverse proxy
            $this->invalidator,
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            false,
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->never())->method('clear');
        $this->httpAdapter->expects($this->once())->method('clear');

        $this->invalidator->expects($this->never())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');
        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->filesystem->expects($this->never())->method('remove');

        $cacheClearer->clearHttpCache();
    }

    public function testClearHttpCacheWithRegisteredButDisabledReverseProxy(): void
    {
        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            false,
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->never())->method('clear');
        $this->httpAdapter->expects($this->once())->method('clear');

        $this->invalidator->expects($this->never())->method('invalidateExpired');
        $this->symfonyCache->expects($this->never())->method('clear');
        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->filesystem->expects($this->never())->method('remove');

        $cacheClearer->clearHttpCache();
    }

    public function testClearWithReverseHttpCacheDisabled(): void
    {
        $cacheClearer = new CacheClearer(
            $this->adapters,
            $this->symfonyCache,
            $this->reverseProxyCache,
            $this->invalidator,
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            false, // reverse http cache disabled
            $this->messageBus,
            $this->logger,
            $this->lock
        );

        $this->appAdapter->expects($this->once())->method('clear');
        $this->httpAdapter->expects($this->once())->method('clear');

        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        // Verify twig directory was removed
        $this->expectRemovals($this->expectedClearRemovals());

        $cacheClearer->clear(true);
    }

    /**
     * clear() removes the twig directory first and then the URL generator files it found
     *
     * @return list<list<string>>
     */
    private function expectedClearRemovals(): array
    {
        return [
            [$this->cacheDir . '/twig'],
            [$this->cacheDir . '/UrlGenerator.php', $this->cacheDir . '/UrlGenerator.php.meta'],
        ];
    }

    /**
     * @param list<list<string>> $expected every Filesystem::remove() call in order, each one as a sorted list of paths
     */
    private function expectRemovals(array $expected): void
    {
        $matcher = $this->exactly(\count($expected));
        $this->filesystem->expects($matcher)
            ->method('remove')
            ->with(static::callback(static function (string|iterable $files) use ($matcher, $expected): bool {
                $files = \is_string($files) ? [$files] : array_values([...$files]);
                sort($files);
                static::assertSame($expected[$matcher->numberOfInvocations() - 1], $files);

                return true;
            }));
    }
}
