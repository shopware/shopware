<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class CacheClearer
{
    /**
     * @var CacheClearerInterface
     */
    protected $cacheClearer;

    /**
     * @var string
     */
    protected $cacheDir;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var CacheItemPoolInterface[]
     */
    protected $adapters;

    /**
     * @var string
     */
    protected $environment;

    public function __construct(
        array $adapters,
        CacheClearerInterface $cacheClearer,
        Filesystem $filesystem,
        string $cacheDir,
        string $environment
    ) {
        $this->adapters = $adapters;
        $this->cacheClearer = $cacheClearer;
        $this->cacheDir = $cacheDir;
        $this->filesystem = $filesystem;
        $this->environment = $environment;
    }

    public function invalidateTags(array $tags): void
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof TagAwareAdapterInterface) {
                $adapter->invalidateTags($tags);
            }
        }
    }

    public function clear(): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->clear();
        }

        if (!is_writable($this->cacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $this->cacheDir));
        }

        $this->cacheClearer->clear($this->cacheDir);
        $this->filesystem->remove($this->cacheDir . '/twig');

        $this->cleanupOldCacheDirectories();
    }

    public function deleteItems(array $keys): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->deleteItems($keys);
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

    private function cleanupOldCacheDirectories(): void
    {
        $finder = new Finder();

        $finder->directories()
            ->name($this->environment . '*')
            ->in(dirname($this->cacheDir) . '/');

        if (!$finder->hasResults()) {
            return;
        }

        $remove = [];
        foreach ($finder as $directory) {
            if ($directory->getPathname() !== $this->cacheDir) {
                $remove[] = $directory->getPathname();
            }
        }

        if (!empty($remove)) {
            $this->filesystem->remove($remove);
        }
    }
}
