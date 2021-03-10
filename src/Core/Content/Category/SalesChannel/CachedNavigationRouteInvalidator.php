<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedNavigationRouteInvalidator implements EventSubscriberInterface
{
    private CacheInvalidationLogger $logger;

    public function __construct(CacheInvalidationLogger $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['invalidate', 2000],
            ],
        ];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $logs = array_merge(
            $this->getChangedCategories($event),
            $this->getChangedEntryPoints($event)
        );

        $this->logger->log($logs);
    }

    private function getChangedCategories(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeysWithPayload(CategoryDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return [];
        }

        $ids = array_map([CachedNavigationRoute::class, 'buildName'], $ids);
        $ids[] = CachedNavigationRoute::BASE_NAVIGATION_TAG;

        return $ids;
    }

    private function getChangedEntryPoints(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeysWithPropertyChange(
            SalesChannelDefinition::ENTITY_NAME,
            ['navigationCategoryId', 'navigationCategoryDepth', 'serviceCategoryId', 'footerCategoryId']
        );

        if (empty($ids)) {
            return [];
        }

        return [CachedNavigationRoute::ALL_TAG];
    }
}
