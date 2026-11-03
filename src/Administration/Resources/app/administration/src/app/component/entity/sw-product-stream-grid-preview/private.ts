import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-product-stream-grid-preview',
    load: () => import('./src/sw-product-stream-grid-preview'),
    public: {
        props: [
            'filters',
            'columns',
            'criteria',
            'showSelection',
            'displayAsGroup',
        ],
        emits: [
            'selection-change',
        ],
        slots: [
            'additional-columns',
            'empty-state',
        ],
        blocks: [
            'sw_product_stream_grid_preview_content',
            'sw_product_stream_grid_preview_search_field_label',
            'sw_product_stream_grid_preview_search_field',
            'sw_product_stream_grid_preview_grid',
            'sw_product_stream_grid_preview_grid_columns',
            'sw_product_stream_grid_preview_grid_column_name',
            'sw_product_stream_grid_preview_grid_column_manufacturer',
            'sw_product_stream_grid_preview_grid_column_active',
            'sw_product_stream_grid_preview_grid_column_price',
            'sw_product_stream_grid_preview_grid_column_stock',
            'sw_product_stream_grid_preview_grid_pagination',
            'sw_product_stream_grid_preview_empty_state',
        ],
        computed: [
            'productRepository',
            'salesChannelRepository',
            'salesChannelCriteria',
            'defaultColumns',
            'productColumns',
            'emptyStateMessage',
            'assetFilter',
            'currencyFilter',
        ],
        methods: [
            'onSearchTermChange',
            'createdComponent',
            'loadSystemDefaultCurrency',
            'loadProducts',
            'onPageChange',
            'getPriceForDefaultCurrency',
            'onSelectionChange',
        ],
    },
});
