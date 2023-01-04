<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Message;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
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
