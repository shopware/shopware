<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(CacheClearer::class)]
class CacheClearerTest extends TestCase
{
    private CacheClearer $cacheClearer;

    /**
     * @var array<string, CacheItemPoolInterface&MockObject>
     */
    private array $adapters;

    private CacheClearerInterface&MockObject $symfonyCache;

    private AbstractReverseProxyGateway&MockObject $reverseProxyCache;

    private CacheInvalidator&MockObject $invalidator;

    private Filesystem $filesystem;

    private MessageBusInterface&MockObject $messageBus;

    private LoggerInterface&MockObject $logger;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->adapters = [
            'app' => $this->createMock(CacheItemPoolInterface::class),
            'http' => $this->createMock(CacheItemPoolInterface::class),
        ];
        $this->symfonyCache = $this->createMock(CacheClearerInterface::class);
        $this->reverseProxyCache = $this->createMock(AbstractReverseProxyGateway::class);
        $this->invalidator = $this->createMock(CacheInvalidator::class);
        $this->filesystem = new Filesystem(); // Use real filesystem
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Create a nested directory structure to avoid scanning system temp directories
        $testBase = sys_get_temp_dir() . '/shopware_test_' . uniqid();
        $this->cacheDir = $testBase . '/cache/test_cache_' . uniqid();

        // Create the cache directory for tests
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

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
            $this->logger
        );
    }

    protected function tearDown(): void
    {
        // Clean up the entire test base directory (includes all nested test directories)
        $testBase = \dirname($this->cacheDir, 2); // Go up two levels to shopware_test_* directory
        if (is_dir($testBase) && str_starts_with(basename($testBase), 'shopware_test_')) {
            $this->filesystem->remove($testBase);
        }

        parent::tearDown();
    }

    public function testClearWithHttpCache(): void
    {
        // Create twig cache directory and files
        $twigDir = $this->cacheDir . '/twig';
        mkdir($twigDir, 0777, true);
        file_put_contents($twigDir . '/test.cache', 'test content');

        // Create URL generator cache files
        file_put_contents($this->cacheDir . '/UrlGenerator.php', '<?php // test');
        file_put_contents($this->cacheDir . '/UrlGenerator.php.meta', 'meta content');

        foreach ($this->adapters as $adapter) {
            $adapter->expects($this->once())->method('clear');
        }

        $this->reverseProxyCache->expects($this->once())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        $this->cacheClearer->clear(true);

        // Verify twig directory was removed
        static::assertDirectoryDoesNotExist($twigDir);
        // Verify URL generator files were removed
        static::assertFileDoesNotExist($this->cacheDir . '/UrlGenerator.php');
        static::assertFileDoesNotExist($this->cacheDir . '/UrlGenerator.php.meta');
    }

    public function testClearWithoutHttpCache(): void
    {
        // Create twig cache directory
        $twigDir = $this->cacheDir . '/twig';
        mkdir($twigDir, 0777, true);

        foreach ($this->adapters as $adapter) {
            $adapter->expects($this->once())->method('clear');
        }

        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        $this->cacheClearer->clear(false);

        // Verify twig directory was removed
        static::assertDirectoryDoesNotExist($twigDir);
    }

    public function testClearWithInvalidatorException(): void
    {
        // Create twig cache directory
        $twigDir = $this->cacheDir . '/twig';
        mkdir($twigDir, 0777, true);

        foreach ($this->adapters as $adapter) {
            $adapter->expects($this->once())->method('clear');
        }

        $exception = new \Exception('Redis not available');
        $this->invalidator->expects($this->once())
            ->method('invalidateExpired')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('critical')
            ->with('Could not clear cache: ' . $exception->getMessage());

        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        $this->cacheClearer->clear();

        // Verify twig directory was removed despite the exception
        static::assertDirectoryDoesNotExist($twigDir);
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
            $this->logger
        );

        foreach ($this->adapters as $adapter) {
            $adapter->expects($this->once())->method('clear');
        }

        $this->reverseProxyCache->expects($this->once())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');

        $this->expectException(AdapterException::class);
        $cacheClearer->clear();
    }

    public function testClearInClusterMode(): void
    {
        // Create twig cache directory that should NOT be deleted in cluster mode
        $twigDir = $this->cacheDir . '/twig';
        mkdir($twigDir, 0777, true);
        file_put_contents($twigDir . '/test.cache', 'test content');

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
            $this->logger
        );

        foreach ($this->adapters as $adapter) {
            $adapter->expects($this->once())->method('clear');
        }

        $this->reverseProxyCache->expects($this->once())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        $cacheClearer->clear();

        // In cluster mode, filesystem operations should not be performed
        static::assertDirectoryExists($twigDir);
        static::assertFileExists($twigDir . '/test.cache');
    }

    public function testClearWithoutReverseProxyCache(): void
    {
        // Create twig cache directory
        $twigDir = $this->cacheDir . '/twig';
        mkdir($twigDir, 0777, true);

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
            $this->logger
        );

        foreach ($this->adapters as $adapter) {
            $adapter->expects($this->once())->method('clear');
        }

        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        $cacheClearer->clear();

        // Verify twig directory was removed
        static::assertDirectoryDoesNotExist($twigDir);
    }

    public function testClearContainerCache(): void
    {
        // Create container cache files to be found by Finder
        $containerFile1 = $this->cacheDir . '/TestContainer.php';
        $containerFile2 = $this->cacheDir . '/CachedContainer.php';
        $containerDir = $this->cacheDir . '/ContainerXYZ';
        $nonContainerFile = $this->cacheDir . '/other.php';

        touch($containerFile1);
        touch($containerFile2);
        mkdir($containerDir, 0777, true);
        touch($nonContainerFile);

        $this->cacheClearer->clearContainerCache();

        // Verify container files/dirs were removed
        static::assertFileDoesNotExist($containerFile1);
        static::assertFileDoesNotExist($containerFile2);
        static::assertDirectoryDoesNotExist($containerDir);
        // Verify non-container file was not removed
        static::assertFileExists($nonContainerFile);
    }

    public function testClearContainerCacheInClusterMode(): void
    {
        // Create container cache files that should NOT be deleted in cluster mode
        $containerFile = $this->cacheDir . '/TestContainer.php';
        touch($containerFile);

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
            $this->logger
        );

        $cacheClearer->clearContainerCache();

        // In cluster mode, files should not be deleted
        static::assertFileExists($containerFile);
    }

    public function testScheduleCacheFolderCleanup(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(CleanupOldCacheFolders::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->cacheClearer->scheduleCacheFolderCleanup();
    }

    public function testDeleteItems(): void
    {
        $keys = ['key1', 'key2', 'key3'];

        foreach ($this->adapters as $adapter) {
            $adapter->expects($this->once())
                ->method('deleteItems')
                ->with($keys);
        }

        $this->cacheClearer->deleteItems($keys);
    }

    public function testPrune(): void
    {
        /** @var PruneableInterface&CacheItemPoolInterface&MockObject $pruneableAdapter */
        $pruneableAdapter = $this->createMock(PruneableInterface::class);
        $pruneableAdapter->expects($this->once())->method('prune');

        /** @var CacheItemPoolInterface&MockObject $nonPruneableAdapter */
        $nonPruneableAdapter = $this->createMock(CacheItemPoolInterface::class);

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
            $this->logger
        );

        $cacheClearer->prune();
    }

    public function testCleanupOldContainerCacheDirectories(): void
    {
        // Create old cache directories in the parent directory (within our test structure)
        $parentDir = \dirname($this->cacheDir);
        $oldCacheDir1 = $parentDir . '/test_old_cache_1';
        $oldCacheDir2 = $parentDir . '/test_another_cache';
        $currentEnvDir = $parentDir . '/test_different'; // same env prefix

        mkdir($oldCacheDir1, 0777, true);
        mkdir($oldCacheDir2, 0777, true);
        mkdir($currentEnvDir, 0777, true);

        $this->cacheClearer->cleanupOldContainerCacheDirectories();

        // Old cache directories with matching environment prefix should be removed
        static::assertDirectoryDoesNotExist($oldCacheDir1);
        static::assertDirectoryDoesNotExist($oldCacheDir2);
        static::assertDirectoryDoesNotExist($currentEnvDir);
        // Current cache directory should still exist
        static::assertDirectoryExists($this->cacheDir);
    }

    public function testCleanupOldContainerCacheDirectoriesInClusterMode(): void
    {
        // Create old cache directory that should NOT be deleted in cluster mode
        $parentDir = \dirname($this->cacheDir);
        $oldCacheDir = $parentDir . '/test_old_cache';
        mkdir($oldCacheDir, 0777, true);

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
            $this->logger
        );

        $cacheClearer->cleanupOldContainerCacheDirectories();

        // In cluster mode, directories should not be deleted
        static::assertDirectoryExists($oldCacheDir);
    }

    public function testClearHttpCacheWithReverseProxy(): void
    {
        $this->reverseProxyCache->expects($this->once())->method('banAll');
        $this->adapters['http']->expects($this->never())->method('clear');

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
            $this->logger
        );

        $this->adapters['http']->expects($this->once())->method('clear');

        $cacheClearer->clearHttpCache();
    }

    public function testClearWithReverseHttpCacheDisabled(): void
    {
        // Create twig cache directory
        $twigDir = $this->cacheDir . '/twig';
        mkdir($twigDir, 0777, true);

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
            $this->logger
        );

        foreach ($this->adapters as $adapter) {
            $adapter->expects($this->once())->method('clear');
        }

        $this->reverseProxyCache->expects($this->never())->method('banAll');
        $this->invalidator->expects($this->once())->method('invalidateExpired');
        $this->symfonyCache->expects($this->once())->method('clear')->with($this->cacheDir);

        $cacheClearer->clear(true);

        // Verify twig directory was removed
        static::assertDirectoryDoesNotExist($twigDir);
    }
}
