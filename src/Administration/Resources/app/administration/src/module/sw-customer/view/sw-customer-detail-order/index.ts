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
    name: 'sw-customer-detail-order',
    load: () => import('./src/sw-customer-detail-order'),
    public: {
        props: [
            'customer',
        ],
        blocks: [
            'sw_customer_detail_order_card',
            'sw_customer_detail_order_card_toolbar',
            'sw_customer_detail_order_add_button',
            'sw_customer_detail_order_card_grid',
            'sw_customer_detail_order_card_grid_columns',
            'sw_customer_detail_order_card_grid_columns_number',
            'sw_customer_detail_order_card_grid_columns_amount',
            'sw_customer_detail_order_card_grid_columns_order_date_time',
            'sw_customer_detail_order_card_grid_columns_action',
            'sw_customer_detail_order_card_grid_columns_action_button',
            'sw_customer_detail_order_card_grid_empty_state',
            'sw_customer_detail_order_card_grid_empty_state_action',
        ],
        computed: [
            'orderColumns',
            'orderRepository',
            'emptyTitle',
            'currencyFilter',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'onChange',
            'getOrderColumns',
            'refreshList',
            'navigateToCreateOrder',
        ],
    },
});
