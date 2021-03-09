<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedShippingMethodRouteInvalidator implements EventSubscriberInterface
{
    private Connection $connection;

    private CacheInvalidationLogger $logger;

    public function __construct(
        CacheInvalidationLogger $logger,
        Connection $connection
    ) {
        $this->logger = $logger;
        $this->connection = $connection;
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
            $this->getChangedShippingMethods($event),
            $this->getChangedAssignments($event)
        );

        $this->logger->log($logs);
    }

    private function getChangedShippingMethods(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(ShippingMethodDefinition::ENTITY_NAME);
        if (empty($ids)) {
            return [];
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_shipping_method WHERE shipping_method_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        return array_map([CachedShippingMethodRoute::class, 'buildName'], $ids);
    }

    private function getChangedAssignments(EntityWrittenContainerEvent $event): array
    {
        //Used to detect changes to the shipping assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelShippingMethodDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map([CachedShippingMethodRoute::class, 'buildName'], $ids);
    }
}
