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
    name: 'sw-order-general-info',
    load: () => import('./src/sw-order-general-info'),
    public: {
        props: [
            'order',
        ],
        emits: [
            'save-edits',
        ],
        blocks: [
            'sw_order_detail_base_general_info',
            'sw_order_detail_base_general_info_summary',
            'sw_order_detail_base_general_info_summary_main',
            'sw_order_detail_base_general_info_summary_main_header',
            'sw_order_detail_base_general_info_summary_main_total',
            'sw_order_detail_base_general_info_summary_sub',
            'sw_order_detail_base_general_info_summary_sub_description',
            'sw_order_detail_base_general_info_summary_sub_last_changed',
            'sw_order_detail_base_general_info_summary_sub_last_changed_time',
            'sw_order_detail_base_general_info_summary_sub_last_changed_user',
            'sw_order_state_change_card_modal',
            'sw_order_detail_base_general_info_order_states',
            'sw_order_detail_base_general_info_order_states_payment',
            'sw_order_detail_base_general_info_order_states_shipping',
            'sw_order_detail_base_general_info_order_states_order',
            'sw_order_detail_base_general_info_order_tags',
        ],
        computed: [
            'isLoading',
            'savedSuccessful',
            'lastChangedUser',
            'lastChangedDateTime',
            'lastChangedByCriteria',
            'orderRepository',
            'orderTagRepository',
            'stateMachineStateRepository',
            'stateMachineStateCriteria',
            'transaction',
            'delivery',
            'currencyFilter',
            'dateFilter',
            'emailIdnFilter',
        ],
        methods: [
            'createdComponent',
            'syncTagCollection',
            'getLiveOrder',
            'onTagAdd',
            'onTagRemove',
            'getAllStates',
            'buildTransitionOptions',
            'backgroundStyle',
            'getTransitionOptions',
            'onStateSelected',
            'onLeaveModalClose',
            'onLeaveModalConfirm',
            'loadHistory',
            'createStateChangeErrorNotification',
        ],
    },
});
