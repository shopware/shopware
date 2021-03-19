<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedCategoryRouteInvalidator implements EventSubscriberInterface
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
            CategoryIndexerEvent::class => [
                ['invalidateIndexedCategories', 2000],
            ],
            EntityWrittenContainerEvent::class => [
                ['invalidateLayouts', 2000],
            ],
        ];
    }

    public function invalidateLayouts(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeys(CmsPageDefinition::ENTITY_NAME);
        if (empty($ids)) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(id)) as id
             FROM category
             WHERE cms_page_id IN (:ids)
             AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->logger->log(
            array_map([CachedCategoryRoute::class, 'buildName'], $ids)
        );
    }

    public function invalidateIndexedCategories(CategoryIndexerEvent $event): void
    {
        $this->logger->log(
            array_map([CachedCategoryRoute::class, 'buildName'], $event->getIds())
        );
    }
}
