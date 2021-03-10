<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedProductDetailRouteInvalidator implements EventSubscriberInterface
{
    private CacheInvalidationLogger $logger;

    private Connection $connection;

    public function __construct(CacheInvalidationLogger $logger, Connection $connection)
    {
        $this->logger = $logger;
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductIndexerEvent::class => 'invalidate',
            EntityWrittenContainerEvent::class => [
                ['invalidateLayouts', 2000],
            ],
        ];
    }

    public function invalidate(ProductIndexerEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $ids = array_filter(array_merge($event->getParentIds(), $event->getIds()));

        $this->logger->log(
            array_map([CachedProductDetailRoute::class, 'buildName'], $ids)
        );
    }

    public function invalidateLayouts(EntityWrittenContainerEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $ids = $event->getPrimaryKeys(CmsPageDefinition::ENTITY_NAME);
        if (empty($ids)) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(id)) as id
             FROM product
             WHERE cms_page_id IN (:ids)
             AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->logger->log(
            array_map([CachedProductDetailRoute::class, 'buildName'], $ids)
        );
    }
}
