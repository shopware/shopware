<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedCurrencyRouteInvalidator implements EventSubscriberInterface
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
        $this->logger->log(array_merge(
            $this->getChangedAssignments($event),
            $this->getChangedCurrencies($event)
        ));
    }

    private function getChangedCurrencies(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(CurrencyDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return [];
        }

        //Used to detect changes to the currency itself and invalidate the route for all sales channels in which the currency is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_currency WHERE currency_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        return array_map([CachedCurrencyRoute::class, 'buildName'], $ids);
    }

    private function getChangedAssignments(EntityWrittenContainerEvent $event): array
    {
        //Used to detect changes to the currency assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelCurrencyDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map([CachedCurrencyRoute::class, 'buildName'], $ids);
    }
}
