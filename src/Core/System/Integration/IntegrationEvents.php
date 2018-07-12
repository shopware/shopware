<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

class IntegrationEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const INTEGRATION_WRITTEN_EVENT = 'integration.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const INTEGRATION_DELETED_EVENT = 'integration.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const INTEGRATION_LOADED_EVENT = 'integration.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const INTEGRATION_SEARCH_RESULT_LOADED_EVENT = 'integration.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const INTEGRATION_AGGREGATION_LOADED_EVENT = 'integration.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const INTEGRATION_ID_SEARCH_RESULT_LOADED_EVENT = 'integration.id.search.result.loaded';
}
