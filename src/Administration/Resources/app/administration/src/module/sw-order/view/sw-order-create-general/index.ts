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
    name: 'sw-order-create-general',
    load: () => import('./src/sw-order-create-general'),
    public: {
        emits: [
            'error',
        ],
        blocks: [
            'sw_order_create_general',
        ],
        computed: [
            'customer',
            'cart',
            'currency',
            'context',
            'isCustomerActive',
            'cartDelivery',
            'cartDeliveryDiscounts',
            'taxStatus',
            'shippingCostsDetail',
            'filteredCalculatedTaxes',
            'displayRounded',
            'orderTotal',
            'currencyFilter',
        ],
        methods: [
            'createdComponent',
            'onSaveItem',
            'onShippingChargeEdited',
            'onRemoveItems',
            'loadCart',
            'sortByTaxRate',
            'onShippingChargeUpdated',
        ],
    },
});
