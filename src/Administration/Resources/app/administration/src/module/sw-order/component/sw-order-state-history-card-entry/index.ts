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
    name: 'sw-order-state-history-card-entry',
    load: () => import('./src/sw-order-state-history-card-entry'),
    public: {
        props: [
            'history',
            'transitionOptions',
            'stateMachineName',
            'title',
            'disabled',
        ],
        blocks: [
            'sw_order_state_card_entry',
            'sw_order_state_card_entry_headline',
            'sw_order_state_card_entry_history',
            'sw_order_state_card_entry_icon',
            'sw_order_state_card_entry_description',
            'sw_order_state_card_entry_date',
            'sw_order_state_card_entry_state_select',
        ],
        computed: [
            'dateFilter',
        ],
        methods: [
            'userDisplayName',
            'integrationDisplayName',
            'getDisplayName',
            'getIconFromState',
            'getIconColorFromState',
            'getBackgroundColorFromState',
        ],
    },
});
