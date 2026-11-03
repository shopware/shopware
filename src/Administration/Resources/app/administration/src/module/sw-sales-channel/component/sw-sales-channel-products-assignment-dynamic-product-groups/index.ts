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
    name: 'sw-sales-channel-products-assignment-dynamic-product-groups',
    load: () => import('./src/sw-sales-channel-products-assignment-dynamic-product-groups'),
    public: {
        props: [
            'salesChannel',
            'containerStyle',
        ],
        emits: [
            'selection-change',
            'product-loading',
        ],
        blocks: [
            'sw_sales_channel_products_assignment_dynamic_product_groups',
            'sw_sales_channel_products_assignment_dynamic_product_groups_info_text',
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing',
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing_container',
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing_section',
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing_search',
            'sw_sales_channel_products_assignment_dynamic_product_groups_card_section_primary',
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing_entity',
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing_bulk',
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing_delete_action',
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing_pagination',
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty',
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty_icon',
        ],
        computed: [
            'productRepository',
            'productStreamRepository',
            'productCriteria',
            'productStreamCriteria',
            'productStreamColumns',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'getProductStreams',
            'onSearch',
            'onPaginate',
            'onOpen',
            'onSelect',
            'getProductsFromProductStreams',
            'getProductStreamFilter',
            'createProductCriteria',
            'getProducts',
        ],
    },
});
