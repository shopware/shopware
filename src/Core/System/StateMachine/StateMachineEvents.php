<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

class StateMachineEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const STATE_MACHINE_WRITTEN_EVENT = 'state_machine.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const STATE_MACHINE_DELETED_EVENT = 'state_machine.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const STATE_MACHINE_LOADED_EVENT = 'state_machine.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const STATE_MACHINE_SEARCH_RESULT_LOADED_EVENT = 'state_machine.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const STATE_MACHINE_AGGREGATION_LOADED_EVENT = 'state_machine.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const STATE_MACHINE_ID_SEARCH_RESULT_LOADED_EVENT = 'state_machine.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const STATE_MACHINE_STATE_WRITTEN_EVENT = 'state_machine_state.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const STATE_MACHINE_STATE_DELETED_EVENT = 'state_machine_state.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const STATE_MACHINE_STATE_LOADED_EVENT = 'state_machine_state.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const STATE_MACHINE_STATE_SEARCH_RESULT_LOADED_EVENT = 'state_machine_state.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const STATE_MACHINE_STATE_AGGREGATION_LOADED_EVENT = 'state_machine_state.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const STATE_MACHINE_STATE_ID_SEARCH_RESULT_LOADED_EVENT = 'state_machine_state.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const STATE_MACHINE_STATE_TRANSLATION_WRITTEN_EVENT = 'state_machine_state_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const STATE_MACHINE_STATE_TRANSLATION_DELETED_EVENT = 'state_machine_state_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const STATE_MACHINE_STATE_TRANSLATION_LOADED_EVENT = 'state_machine_state_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const STATE_MACHINE_STATE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'state_machine_state_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const STATE_MACHINE_STATE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'state_machine_state_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const STATE_MACHINE_STATE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'state_machine_state_translation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const STATE_MACHINE_TRANSITION_WRITTEN_EVENT = 'state_machine_transition.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const STATE_MACHINE_TRANSITION_DELETED_EVENT = 'state_machine_transition.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const STATE_MACHINE_TRANSITION_LOADED_EVENT = 'state_machine_transition.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const STATE_MACHINE_TRANSITION_SEARCH_RESULT_LOADED_EVENT = 'state_machine_transition.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const STATE_MACHINE_TRANSITION_AGGREGATION_LOADED_EVENT = 'state_machine_transition.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const STATE_MACHINE_TRANSITION_ID_SEARCH_RESULT_LOADED_EVENT = 'state_machine_transition.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const STATE_MACHINE_HISTORY_WRITTEN_EVENT = 'state_machine_history.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const STATE_MACHINE_HISTORY_DELETED_EVENT = 'state_machine_history.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const STATE_MACHINE_HISTORY_LOADED_EVENT = 'state_machine_history.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const STATE_MACHINE_HISTORY_SEARCH_RESULT_LOADED_EVENT = 'state_machine_history.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const STATE_MACHINE_HISTORY_AGGREGATION_LOADED_EVENT = 'state_machine_history.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const STATE_MACHINE_HISTORY_ID_SEARCH_RESULT_LOADED_EVENT = 'state_machine_history.id.search.result.loaded';
}
