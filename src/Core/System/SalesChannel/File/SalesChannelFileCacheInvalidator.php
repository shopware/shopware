<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileCacheInvalidator implements EventSubscriberInterface
{
    private const DISCOVERY_TAG = 'sales-channel-file-discovery';

    public function __construct(private readonly CacheInvalidator $cacheInvalidator)
    {
    }

    public static function buildCacheTag(string $salesChannelFileId): string
    {
        // A sales_channel_file row is the persisted ownership boundary for exactly one public file
        // in one sales channel. Runtime response invalidation only needs the row-specific tag;
        // template discovery has its own tag for extension and update lifecycle changes.
        return 'sales-channel-file-' . $salesChannelFileId;
    }

    public static function buildDiscoveryCacheTag(): string
    {
        return self::DISCOVERY_TAG;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel_file.written' => 'invalidate',
            'sales_channel_file.deleted' => 'invalidate',
            AppActivatedEvent::class => 'invalidateDiscovery',
            AppDeactivatedEvent::class => 'invalidateDiscovery',
            AppUpdatedEvent::class => 'invalidateDiscovery',
            PluginPostActivateEvent::class => 'invalidateDiscovery',
            PluginPostDeactivateEvent::class => 'invalidateDiscovery',
            PluginPostUpdateEvent::class => 'invalidateDiscovery',
            UpdatePostFinishEvent::class => 'invalidateDiscovery',
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

    public function invalidateDiscovery(): void
    {
        // Template discovery caches the resolved Twig chain. Extension/app lifecycle changes and Shopware
        // updates can change that chain, so clear the single discovery tag immediately after those events.
        $this->cacheInvalidator->invalidate([self::buildDiscoveryCacheTag()], true);
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
