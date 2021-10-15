<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Content\Flow\Events\FlowIndexerEvent;

class FlowEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const FLOW_WRITTEN_EVENT = 'flow.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const FLOW_DELETED_EVENT = 'flow.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const FLOW_LOADED_EVENT = 'flow.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const FLOW_SEARCH_RESULT_LOADED_EVENT = 'flow.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const FLOW_AGGREGATION_LOADED_EVENT = 'flow.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const FLOW_ID_SEARCH_RESULT_LOADED_EVENT = 'flow.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Content\Flow\Events\FlowIndexerEvent")
     */
    public const FLOW_INDEXER_EVENT = FlowIndexerEvent::class;

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const FLOW_SEQUENCE_WRITTEN_EVENT = 'flow_sequence.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const FLOW_SEQUENCE_DELETED_EVENT = 'flow_sequence.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const FLOW_SEQUENCE_LOADED_EVENT = 'flow_sequence.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const FLOW_SEQUENCE_SEARCH_RESULT_LOADED_EVENT = 'flow_sequence.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const FLOW_SEQUENCE_AGGREGATION_LOADED_EVENT = 'flow_sequence.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const FLOW_SEQUENCE_ID_SEARCH_RESULT_LOADED_EVENT = 'flow_sequence.id.search.result.loaded';
}
