import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-order-state-history-card',
    load: () => import('./src/sw-order-state-history-card'),
    public: {
        props: [
            'title',
            'order',
            'isLoading',
        ],
        emits: [
            'options-change',
            'order-state-change',
        ],
        blocks: [
            'sw_order_state_history_card',
            'sw_order_state_change_card_modal',
            'sw_order_state_history_card_container',
            'sw_order_state_history_card_transaction',
            'sw_order_state_history_card_delivery',
            'sw_order_state_history_card_order',
        ],
        computed: [
            'stateMachineStateRepository',
            'mailTemplateRepository',
            'stateMachineHistoryRepository',
            'transaction',
            'delivery',
            'stateMachineHistoryCriteria',
        ],
        methods: [
            'createdComponent',
            'loadHistory',
            'getStateHistoryEntries',
            'fetchEntries',
            'buildStateHistory',
            'getTransitionOptions',
            'getAllStates',
            'stateMachineStateCriteria',
            'buildTransitionOptions',
            'onOrderStateSelected',
            'onCancelCreation',
            'onTransactionStateSelected',
            'onDeliveryStateSelected',
            'onLeaveModalClose',
            'onLeaveModalConfirm',
            'createStateChangeErrorNotification',
        ],
    },
});
