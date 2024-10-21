<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

/**
 * @internal
 */
#[Package('core')]
class TestCacheClearer
{
    protected CacheClearerInterface $cacheClearer;

    protected string $cacheDir;

    protected Filesystem $filesystem;

    /**
     * @var CacheItemPoolInterface[]
     */
    protected array $adapters;

    /**
     * @param CacheItemPoolInterface[] $adapters
     */
    public function __construct(
        array $adapters,
        CacheClearerInterface $cacheClearer,
        string $cacheDir
    ) {
        $this->adapters = $adapters;
        $this->cacheClearer = $cacheClearer;
        $this->cacheDir = $cacheDir;
    }

    public function clear(): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->clear();
        }

        $this->cacheClearer->clear($this->cacheDir);
    }
}
