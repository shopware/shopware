<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileCacheInvalidator implements EventSubscriberInterface
{
    public function __construct(private readonly CacheInvalidator $cacheInvalidator)
    {
    }

    public static function buildCacheTag(string $salesChannelFileId): string
    {
        // A sales_channel_file row is the persisted ownership boundary for exactly one public file
        // in one sales channel. Template discovery changes come from code and are deployed with a
        // full cache clear, so runtime invalidation only needs the row-specific tag.
        return 'sales-channel-file-' . $salesChannelFileId;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel_file.written' => 'invalidate',
            'sales_channel_file.deleted' => 'invalidate',
        ];
    }

    public function invalidate(EntityWrittenEvent|EntityDeletedEvent $event): void
    {
        $tags = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $id = $this->getPrimaryKeyId($writeResult);

            if ($id === null) {
                continue;
            }

            $tags[] = self::buildCacheTag($id);
        }

        // Force immediate invalidation because Admin edits should update the public file response directly.
        // This only purges row-specific tags for actually touched files, so it cannot fan out into a cache storm.
        $this->cacheInvalidator->invalidate(array_values(array_unique($tags)), true);
    }

    private function getPrimaryKeyId(EntityWriteResult $writeResult): ?string
    {
        $primaryKey = $writeResult->getPrimaryKey();

        if (\is_string($primaryKey)) {
            return $primaryKey;
        }

        return $primaryKey['id'] ?? null;
    }
}
