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
    name: 'sw-order-create-details-footer',
    load: () => import('./src/sw-order-create-details-footer'),
    public: {
        props: [
            'cart',
            'customer',
            'isCustomerActive',
        ],
        emits: [
            'loading-change',
        ],
        blocks: [
            'sw_order_create_details_footer',
            'sw_order_create_details_footer_sales_channel',
            'sw_order_create_details_footer_payment_method',
            'sw_order_create_details_footer_shipping_method',
            'sw_order_create_details_footer_order_language',
            'sw_order_create_details_footer_currency',
        ],
        computed: [
            'context',
            'salesChannelId',
            'salesChannelCriteria',
            'paymentMethodCriteria',
            'currencyRepository',
            'currentCurrencyId',
            'defaultSalesChannel',
            'isCartTokenAvailable',
        ],
        methods: [
            'updateContext',
            'updateOrderContext',
            'updateCustomerContext',
            'getCart',
            'getCurrency',
        ],
    },
});
