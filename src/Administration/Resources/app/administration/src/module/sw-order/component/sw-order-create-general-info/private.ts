import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-order-create-general-info',
    load: () => import('./src/sw-order-create-general-info'),
    public: {
        props: [
            'cart',
            'context',
            'isLoading',
        ],
        blocks: [
            'sw_order_create_general_info',
            'sw_order_create_general_info_summary',
            'sw_order_create_general_info_summary_main',
            'sw_order_create_general_info_summary_sub',
            'sw_order_create_general_info_order_states',
            'sw_order_create_general_info_order_state_payment',
            'sw_order_create_general_info_order_state_delivery',
            'sw_order_create_general_info_order_state_order',
            'sw_order_create_general_info_order_tags',
        ],
        computed: [
            'summaryMainHeader',
            'paymentMethodName',
            'shippingMethodName',
            'currencyFilter',
        ],
    },
});
