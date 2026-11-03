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
    name: 'sw-order-delivery-metadata',
    load: () => import('./src/sw-order-delivery-metadata'),
    public: {
        props: [
            'delivery',
            'order',
            'title',
            'isLoading',
        ],
        blocks: [
            'sw_order_delivery_metadata',
            'sw_order_delivery_metadata_deliveries',
            'sw_order_delivery_metadata_deliveries_data',
            'sw_order_delivery_metadata_shipping_method',
            'sw_order_delivery_metadata_delivery_date',
            'sw_order_delivery_metadata_shipping_costs',
            'sw_order_delivery_metadata_slot',
            'sw_order_delivery_metadata_shipping_address',
            'sw_order_delivery_metadata_delivery_phone_number',
        ],
        computed: [
            'currencyFilter',
            'dateFilter',
        ],
        methods: [
            'createdComponent',
            'renderFormattingAddress',
        ],
    },
});
