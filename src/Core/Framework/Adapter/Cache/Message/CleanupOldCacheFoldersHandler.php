<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Message;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

/**
 * @package core
 *
 * @internal
 */
final class CleanupOldCacheFoldersHandler implements MessageSubscriberInterface
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

    /**
     * @return iterable<string>
     */
    public static function getHandledMessages(): iterable
    {
        return [CleanupOldCacheFolders::class];
    }
}
