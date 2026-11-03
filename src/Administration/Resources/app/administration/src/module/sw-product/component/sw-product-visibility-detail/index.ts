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
    name: 'sw-product-visibility-detail',
    load: () => import('./src/sw-product-visibility-detail'),
    public: {
        props: [
            'disabled',
        ],
        blocks: [
            'sw_product_visibility_detail',
            'sw_product_visibility_detail_columns',
            'sw_product_visibility_detail_columns_sales_channel',
            'sw_product_visibility_detail_columns_sales_channel_label',
            'sw_product_visibility_detail_columns_all',
            'sw_product_visibility_detail_columns_search_only',
            'sw_product_visibility_detail_columns_link_only',
            'sw_product_visibility_detail_pagination',
        ],
        computed: [
            'product',
            'truncateFilter',
            'filteredItems',
            'names',
        ],
        methods: [
            'createdComponent',
            'onPageChange',
            'changeVisibilityValue',
        ],
    },
});
