<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class TestCacheClearer
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
