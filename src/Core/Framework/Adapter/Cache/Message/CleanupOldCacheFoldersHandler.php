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
    public function __construct(private readonly CacheClearer $cacheClearer)
    {
    }

    public function __invoke(CleanupOldCacheFolders $message): void
    {
        $this->cacheClearer->cleanupOldContainerCacheDirectories();
    }
}
