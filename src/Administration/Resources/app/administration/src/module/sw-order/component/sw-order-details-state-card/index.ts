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
    name: 'sw-order-details-state-card',
    load: () => import('./src/sw-order-details-state-card'),
    public: {
        props: [
            'order',
            'title',
            'entity',
            'stateLabel',
            'isLoading',
            'disabled',
            'position',
        ],
        emits: [
            'show-status-history',
            'save-edits',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_order_detail_state_card',
            'sw_order_detail_state_card_state',
            'sw_order_detail_state_card_state_select',
            'sw_order_detail_state_card_state_history_text',
            'sw_order_detail_state_card_state_history_text_label',
            'sw_order_detail_state_card_state_history_text_internal_comment',
            'sw_order_detail_state_card_state_history_button',
            'sw_order_state_change_card_state_modal',
            'sw_order_state_change_card_divider',
            'sw_order_state_change_card_content',
        ],
        computed: [
            'stateMachineStateRepository',
            'stateMachineHistoryRepository',
            'stateMachineStateCriteria',
            'stateMachineHistoryCriteria',
            'entityName',
            'stateName',
            'stateSelectBackgroundStyle',
            'stateTransitionMethod',
            'cardPosition',
            'lastChangeAuthorLabel',
        ],
        methods: [
            'createdComponent',
            'onShowStatusHistory',
            'getTransitionOptions',
            'buildTransitionOptions',
            'onStateSelected',
            'onLeaveModalClose',
            'onLeaveModalConfirm',
            'createStateChangeErrorNotification',
            'getLastChange',
        ],
    },
});
