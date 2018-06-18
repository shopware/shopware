<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog;

class CatalogEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CATALOG_WRITTEN_EVENT = 'catalog.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CATALOG_DELETED_EVENT = 'catalog.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CATALOG_LOADED_EVENT = 'catalog.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CATALOG_SEARCH_RESULT_LOADED_EVENT = 'catalog.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CATALOG_AGGREGATION_LOADED_EVENT = 'catalog.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CATALOG_ID_SEARCH_RESULT_LOADED_EVENT = 'catalog.id.search.result.loaded';
}