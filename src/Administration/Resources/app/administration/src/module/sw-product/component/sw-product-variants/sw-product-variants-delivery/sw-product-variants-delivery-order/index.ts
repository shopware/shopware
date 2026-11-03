import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-product-variants-delivery-order',
    load: () => import('./src/sw-product-variants-delivery-order'),
    public: {
        props: [
            'product',
            'selectedGroups',
        ],
        blocks: [
            'sw_product_variants_delivery_order',
            'sw_product_variants_delivery_order_groups',
            'sw_product_variants_delivery_order_groups_sw_tree',
            'sw_product_variants_delivery_order_loader',
        ],
        methods: [
            'mountedComponent',
            'createOrderObjects',
            'getOptionsForGroup',
            'orderChanged',
        ],
    },
});
