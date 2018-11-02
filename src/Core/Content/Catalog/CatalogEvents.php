<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog;

class CatalogEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CATALOG_WRITTEN_EVENT = 'catalog.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CATALOG_DELETED_EVENT = 'catalog.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CATALOG_LOADED_EVENT = 'catalog.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CATALOG_SEARCH_RESULT_LOADED_EVENT = 'catalog.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CATALOG_AGGREGATION_LOADED_EVENT = 'catalog.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CATALOG_ID_SEARCH_RESULT_LOADED_EVENT = 'catalog.id.search.result.loaded';
}
