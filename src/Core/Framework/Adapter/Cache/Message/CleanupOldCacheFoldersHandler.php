<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Message;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @package core
 *
 * @internal
 */
#[AsMessageHandler]
final class CleanupOldCacheFoldersHandler
{
    private CacheClearer $cacheClearer;

    public function __construct(CacheClearer $cacheClearer)
    {
        $this->cacheClearer = $cacheClearer;
    }

    public function __invoke(CleanupOldCacheFolders $message): void
    {
        $this->cacheClearer->cleanupOldContainerCacheDirectories();
    }
}
