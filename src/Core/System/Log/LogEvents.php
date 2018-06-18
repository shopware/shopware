<?php declare(strict_types=1);

namespace Shopware\Core\System\Log;

class LogEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const LOG_WRITTEN_EVENT = 'log.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const LOG_DELETED_EVENT = 'log.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const LOG_LOADED_EVENT = 'log.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const LOG_SEARCH_RESULT_LOADED_EVENT = 'log.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const LOG_AGGREGATION_LOADED_EVENT = 'log.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const LOG_ID_SEARCH_RESULT_LOADED_EVENT = 'log.id.search.result.loaded';
}