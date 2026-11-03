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
    name: 'sw-order-product-select',
    load: () => import('./src/sw-order-product-select'),
    public: {
        props: [
            'item',
            'salesChannelId',
            'taxStatus',
        ],
        slots: [
            'selection-label-property',
            'result-item',
        ],
        blocks: [
            'sw_order_product_select',
            'sw_order_product_select_blank_item',
            'sw_order_product_select_existing_product',
            'sw_order_product_select_results_variant_info',
            'sw_order_product_select_results_list_result_label',
            'sw_order_product_show_product',
        ],
        computed: [
            'productRepository',
            'lineItemTypes',
            'lineItemPriceTypes',
            'isShownProductSelect',
            'isShownItemLabelInput',
            'contextWithInheritance',
            'productCriteria',
        ],
        methods: [
            'onItemChanged',
        ],
    },
});
