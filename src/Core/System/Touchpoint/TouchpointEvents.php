<?php declare(strict_types=1);

namespace TouchpointDefinition;

class TouchpointEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const TOUCHPOINT_WRITTEN_EVENT = 'touchpoint.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const TOUCHPOINT_DELETED_EVENT = 'touchpoint.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const TOUCHPOINT_LOADED_EVENT = 'touchpoint.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const TOUCHPOINT_SEARCH_RESULT_LOADED_EVENT = 'touchpoint.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const TOUCHPOINT_AGGREGATION_LOADED_EVENT = 'touchpoint.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const TOUCHPOINT_ID_SEARCH_RESULT_LOADED_EVENT = 'touchpoint.id.search.result.loaded';
}