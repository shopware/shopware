<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @final
 */
#[Package('framework')]
class CacheClearer
{
    private const LOCK_TTL = 30;
    private const LOCK_KEY_CONTAINER = 'container-cache-directories';

    /**
     * @internal
     *
     * @param CacheItemPoolInterface[] $adapters
     */
    public function __construct(
        private readonly array $adapters,
        private readonly CacheClearerInterface $cacheClearer,
        private readonly ?AbstractReverseProxyGateway $reverseProxyCache,
        private readonly CacheInvalidator $invalidator,
        private readonly Filesystem $filesystem,
        private readonly string $cacheDir,
        private readonly string $environment,
        private readonly bool $clusterMode,
        private readonly bool $reverseHttpCacheEnabled,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function clear(bool $clearHttp = true): void
    {
        $this->clearObjectCache();

        if ($clearHttp && $this->reverseHttpCacheEnabled) {
            $this->reverseProxyCache?->banAll();
        }

        try {
            $this->invalidator->invalidateExpired();
        } catch (\Throwable $e) {
            // redis not available atm (in pipeline or build process)
            $this->logger->critical('Could not clear cache: ' . $e->getMessage());
        }

        if (!is_writable($this->cacheDir)) {
            throw AdapterException::cacheDirectoryError($this->cacheDir);
        }

        $this->cacheClearer->clear($this->cacheDir);

        if ($this->clusterMode) {
            // In cluster mode we can't delete caches on the filesystem
            // because this only runs on one node in the cluster
            return;
        }

        $this->filesystem->remove($this->cacheDir . '/twig');
        $this->cleanupUrlGeneratorCacheFiles();

        $this->cleanupOldContainerCacheDirectories();
    }

    public function clearContainerCache(): void
    {
        if ($this->clusterMode) {
            // In cluster mode we can't delete caches on the filesystem
            // because this only runs on one node in the cluster
            return;
        }

        $finder = (new Finder())->in($this->cacheDir)->name('*Container*')->depth(0);
        $containerCaches = [];

        foreach ($finder->getIterator() as $containerPaths) {
            $containerCaches[] = $containerPaths->getRealPath();
        }

        $this->lock(function () use ($containerCaches): void {
            $this->filesystem->remove($containerCaches);
        }, self::LOCK_KEY_CONTAINER, self::LOCK_TTL);
    }

    public function scheduleCacheFolderCleanup(): void
    {
        $this->messageBus->dispatch(new CleanupOldCacheFolders());
    }

    /**
     * @param list<string> $keys
     */
    public function deleteItems(array $keys): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->deleteItems($keys);
        }
    }

    public function clearObjectCache(): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->clear();
        }
    }

    public function prune(): void
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof PruneableInterface) {
                $adapter->prune();
            }
        }
    }

    public function cleanupOldContainerCacheDirectories(): void
    {
        if ($this->clusterMode) {
            // In cluster mode we can't delete caches on the filesystem
            // because this only runs on one node in the cluster
            return;
        }

        $finder = (new Finder())
            ->directories()
            ->name($this->environment . '*')
            ->in(\dirname($this->cacheDir) . '/');

        if (!$finder->hasResults()) {
            return;
        }
        $remove = [];
        foreach ($finder->getIterator() as $directory) {
            if ($directory->getPathname() !== $this->cacheDir) {
                $remove[] = $directory->getPathname();
            }
        }

        if ($remove !== []) {
            $this->lock(function () use ($remove): void {
                $this->filesystem->remove($remove);
            }, self::LOCK_KEY_CONTAINER, self::LOCK_TTL);
        }
    }

    public function clearHttpCache(): void
    {
        $this->reverseProxyCache?->banAll();

        // if reverse proxy is not enabled, clear the http pool
        if ($this->reverseProxyCache === null) {
            $this->adapters['http']->clear();
        }
    }

    /**
     * Locks the execution of the closure to prevent concurrent executions.
     *
     * @see https://symfony.com/doc/current/components/lock.html
     */
    private function lock(\Closure $closure, string $key, int $timeToLive): void
    {
        $lock = $this->lockFactory->createLock('cache-clearer::' . $key, $timeToLive);

        // The execution is blocked until the key is found or the time to live is reached.
        if ($lock->acquire(true)) {
            $closure();

            $lock->release();
        }
    }

    private function cleanupUrlGeneratorCacheFiles(): void
    {
        $finder = (new Finder())
            ->in($this->cacheDir)
            ->files()
            ->name(['UrlGenerator.php', 'UrlGenerator.php.meta']);

        if (!$finder->hasResults()) {
            return;
        }

        $files = iterator_to_array($finder->getIterator());

        if (\count($files) > 0) {
            $this->filesystem->remove(array_map(static fn (\SplFileInfo $file): string => $file->getPathname(), $files));
        }
    }
}
