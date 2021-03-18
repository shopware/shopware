<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Events\ProductChangedEventInterface;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CachedProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\CachedProductListingRoute;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedProductRouteInvalidator implements EventSubscriberInterface
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
            ProductIndexerEvent::class => [
                ['invalidateSearch', 2000],
                ['invalidateListings', 2001],
                ['invalidateProductIds', 2002],
                ['invalidateStreamListings', 2003],
                ['invalidateDetailRoute', 2004],
            ],
            ProductNoLongerAvailableEvent::class => [
                ['invalidateSearch', 2000],
                ['invalidateListings', 2001],
                ['invalidateProductIds', 2002],
                ['invalidateStreamListings', 2003],
                ['invalidateDetailRoute', 2004],
            ],
            CategoryIndexerEvent::class => [
                ['invalidateIndexedCategories', 2000],
            ],
            EntityWrittenContainerEvent::class => [
                ['invalidateProductAssignment', 2000],
                ['invalidateManufacturerFilters', 2001],
                ['invalidatePropertyFilters', 2002],
                ['invalidateLayouts', 2003],
                ['invalidateCrossSellingRoute', 2004],
            ],
        ];
    }

    public static function buildProductTag(string $id): string
    {
        return 'product-' . $id;
    }

    public static function buildStreamTag(string $id): string
    {
        return 'product-stream-' . $id;
    }

    public function invalidateProductIds(ProductChangedEventInterface $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $this->logger->log(
            array_map([self::class, 'buildProductTag'], $event->getIds())
        );
    }

    public function invalidateStreamIds(EntityWrittenContainerEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $this->logger->log(
            array_map([self::class, 'buildStreamTag'], $event->getPrimaryKeys(ProductStreamDefinition::ENTITY_NAME))
        );
    }

    public function invalidateSearch(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }
        $this->logger->log([
            'product-suggest-route',
            'product-search-route',
        ]);
    }

    public function invalidateDetailRoute(ProductChangedEventInterface $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $this->logger->log(
            array_map([CachedProductDetailRoute::class, 'buildName'], $event->getIds())
        );
    }

    public function invalidateManufacturerFilters(EntityWrittenContainerEvent $event): void
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

    public function invalidatePropertyFilters(EntityWrittenContainerEvent $event): void
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

    public function invalidateProductAssignment(EntityWrittenContainerEvent $event): void
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

    public function invalidateListings(ProductChangedEventInterface $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM product_category_tree
             WHERE product_id IN (:ids)
             AND product_version_id = :version
             AND category_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($event->getIds()), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->logger->log(
            array_map([CachedProductListingRoute::class, 'buildName'], $ids)
        );
    }

    public function invalidateStreamListings(ProductChangedEventInterface $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(id))
             FROM category
             INNER JOIN product_stream_mapping ON category.product_stream_id = product_stream_mapping.product_stream_id
             WHERE product_stream_mapping.product_id IN (:ids)
             AND product_stream_mapping.product_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($event->getIds()), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
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

    public function invalidateCrossSellingRoute(EntityWrittenContainerEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $ids = $event->getPrimaryKeys(ProductCrossSellingDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_id)) FROM product_cross_selling WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->logger->log(
            array_map([CachedProductCrossSellingRoute::class, 'buildName'], $ids)
        );
    }
}
