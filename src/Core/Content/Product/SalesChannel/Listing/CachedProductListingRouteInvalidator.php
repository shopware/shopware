<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedProductListingRouteInvalidator implements EventSubscriberInterface
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
            ProductIndexerEvent::class => 'invalidateIndexedProducts',
            CategoryIndexerEvent::class => 'invalidateIndexedCategories',
            EntityWrittenContainerEvent::class => [
                ['invalidateAssignments', 2000],
                ['invalidateManufacturers', 2001],
                ['invalidateProperties', 2002],
                ['invalidateLayouts', 2003],
            ],
        ];
    }

    public function invalidateManufacturers(EntityWrittenContainerEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $ids = $event->getPrimaryKeys(ProductManufacturerDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM product_category_tree
                INNER JOIN product ON product.id = product_category_tree.product_id AND product_category_tree.product_version_id = product.version_id
             WHERE product.product_manufacturer_id IN (:ids)
             AND product.version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->logger->log(
            array_map([CachedProductListingRoute::class, 'buildName'], $ids)
        );
    }

    public function invalidateProperties(EntityWrittenContainerEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $ids = $event->getPrimaryKeys(PropertyGroupDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM product_category_tree
                INNER JOIN product_property ON product_category_tree.product_id = product_property.product_id AND product_category_tree.product_version_id = product_property.product_version_id
                INNER JOIN property_group_option ON property_group_option.id = product_property.property_group_option_id
             WHERE property_group_option.property_group_id IN (:ids)
             AND product_category_tree.product_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->logger->log(
            array_map([CachedProductListingRoute::class, 'buildName'], $ids)
        );
    }

    public function invalidateAssignments(EntityWrittenContainerEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        //Used to detect changes to the product category assignment
        $ids = $event->getPrimaryKeys(ProductCategoryDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'categoryId');

        $this->logger->log(
            array_map([CachedProductListingRoute::class, 'buildName'], $ids)
        );
    }

    public function invalidateIndexedProducts(ProductIndexerEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $ids = array_filter(array_unique(array_merge($event->getIds(), $event->getParentIds(), $event->getChildrenIds())));

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM product_category_tree
             WHERE product_id IN (:ids)
             AND product_version_id = :version
             AND category_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->logger->log(
            array_map([CachedProductListingRoute::class, 'buildName'], $ids)
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
             FROM category
             WHERE cms_page_id IN (:ids)
             AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->logger->log(
            array_map([CachedProductListingRoute::class, 'buildName'], $ids)
        );
    }

    public function invalidateIndexedCategories(CategoryIndexerEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }
        $this->logger->log(
            array_map([CachedProductListingRoute::class, 'buildName'], $event->getIds())
        );
    }

    public function getChangedProducts(ProductIndexerEvent $event): array
    {
        $ids = array_filter(array_unique(array_merge($event->getIds(), $event->getParentIds(), $event->getChildrenIds())));

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM product_category_tree
             WHERE product_id IN (:ids)
             AND product_version_id = :version
             AND category_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        return array_map([CachedProductListingRoute::class, 'buildName'], $ids);
    }
}
