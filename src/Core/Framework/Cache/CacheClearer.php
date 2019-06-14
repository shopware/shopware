<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Cache;

use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class CacheClearer
{
    /**
     * @var TagAwareAdapterInterface
     */
    private $appCache;

    /**
     * @var CacheClearerInterface
     */
    private $cacheClearer;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(
        TagAwareAdapterInterface $appCache,
        CacheClearerInterface $cacheClearer,
        Filesystem $filesystem,
        string $cacheDir
    ) {
        $this->appCache = $appCache;
        $this->cacheClearer = $cacheClearer;
        $this->cacheDir = $cacheDir;
        $this->filesystem = $filesystem;
    }

    public function clear(): void
    {
        $this->appCache->clear();

        if (!is_writable($this->cacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $this->cacheDir));
        }

        $this->cacheClearer->clear($this->cacheDir);

        $this->filesystem->remove($this->cacheDir . '/twig');
    }
}
