import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-sales-channel-products-assignment-single-products',
    load: () => import('./src/sw-sales-channel-products-assignment-single-products'),
    public: {
        props: [
            'salesChannel',
            'containerStyle',
        ],
        emits: [
            'selection-change',
        ],
        blocks: [
            'sw_sales_channel_products_assignment_single_products',
            'sw_sales_channel_products_assignment_single_products_secondary',
            'sw_sales_channel_products_assignment_single_products_search_field',
            'sw_sales_channel_products_assignment_single_products_primary',
            'sw_sales_channel_products_assignment_single_products_primary_listing',
            'sw_sales_channel_products_assignment_single_products_primary_listing_pagination',
            'sw_sales_channel_products_assignment_single_products_primary_empty_state',
        ],
        computed: [
            'productRepository',
            'productCriteria',
            'productColumns',
            'assetFilter',
        ],
        methods: [
            'getProducts',
            'onChangeSearchTerm',
            'onSelectionChange',
            'onChangePage',
        ],
    },
});
