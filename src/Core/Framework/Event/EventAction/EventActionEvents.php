<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventAction;

use Shopware\Core\Framework\Log\Package;
/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
 * @package business-ops
 */
#[Package('business-ops')]
class EventActionEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const EVENT_ACTION_WRITTEN_EVENT = 'event_action.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const EVENT_ACTION_DELETED_EVENT = 'event_action.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const EVENT_ACTION_LOADED_EVENT = 'event_action.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const EVENT_ACTION_SEARCH_RESULT_LOADED_EVENT = 'event_action.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const EVENT_ACTION_AGGREGATION_LOADED_EVENT = 'event_action.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const EVENT_ACTION_ID_SEARCH_RESULT_LOADED_EVENT = 'event_action.id.search.result.loaded';
}
