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
    name: 'sw-order-state-history-modal',
    load: () => import('./src/sw-order-state-history-modal'),
    public: {
        props: [
            'order',
            'isLoading',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_order_state_history_modal',
            'sw_order_state_history_modal_content',
            'sw_order_state_history_modal_content_columns_created_at',
            'sw_order_state_history_modal_content_columns_entity',
            'sw_order_state_history_modal_content_columns_user',
            'sw_order_state_history_modal_content_columns_order_state',
            'sw_order_state_history_modal_content_columns_delivery_state',
            'sw_order_state_history_modal_content_columns_transaction_state',
            'sw_order_state_history_modal_content_columns_internal_comment',
            'sw_order_state_history_modal_actions',
            'sw_order_state_history_modal_action_close',
        ],
        computed: [
            'stateMachineHistoryRepository',
            'stateHistory',
            'stateMachineHistoryCriteria',
            'columns',
            'hasMultipleTransactions',
            'statesLoading',
        ],
        methods: [
            'createdComponent',
            'loadHistory',
            'getStateHistoryEntries',
            'buildStateHistory',
            'createEntry',
            'getVariantState',
            'onClose',
            'onPageChange',
            'enumerateTransaction',
            'getStateChangeAuthor',
        ],
    },
});
