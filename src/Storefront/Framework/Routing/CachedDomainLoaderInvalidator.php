<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\SalesChannelMaintenanceCacheTag;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class CachedDomainLoaderInvalidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly CacheInvalidator $logger)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['invalidate', 2000],
            ],
        ];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        $salesChannelEvent = $event->getEventByEntityName(SalesChannelDefinition::ENTITY_NAME);
        if (!$salesChannelEvent && !$event->getEventByEntityName(ThemeSalesChannelDefinition::ENTITY_NAME)) {
            return;
        }

        $tags = [
            CachedDomainLoader::CACHE_KEY,
            CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY,
        ];

        foreach ($salesChannelEvent?->getIds() ?? [] as $salesChannelId) {
            if (\is_string($salesChannelId)) {
                $tags[] = SalesChannelMaintenanceCacheTag::build($salesChannelId);
            }
        }

        $this->logger->invalidate($tags, true);
    }
}
