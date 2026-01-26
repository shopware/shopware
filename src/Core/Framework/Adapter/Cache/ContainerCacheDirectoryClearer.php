<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

/**
 * Cleans up cache directories from other kernel hash variants.
 *
 * When plugins are installed/uninstalled, the kernel cache hash changes.
 * This leaves orphaned cache directories that Symfony's cache:clear doesn't remove
 * because it only clears the current kernel's cache directory.
 *
 * This service is registered with the kernel.cache_clearer tag to be called
 * automatically during Symfony's cache:clear command.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContainerCacheDirectoryClearer implements CacheClearerInterface
{
    public function __construct(
        private readonly CacheClearer $cacheClearer,
    ) {
    }

    public function clear(string $cacheDir): void
    {
        $this->cacheClearer->cleanupOldContainerCacheDirectories();
    }
}
