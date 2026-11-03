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
    name: 'sw-product-stream-modal-preview',
    load: () => import('./src/sw-product-stream-modal-preview'),
    public: {
        props: [
            'filters',
            'defaultLimit',
            'defaultSorting',
            'displayAsGroup',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_product_stream_modal_preview',
            'sw_product_stream_modal_preview_content',
            'sw_product_stream_modal_preview_content_header',
            'sw_product_stream_modal_preview_search_field_label',
            'sw_product_stream_modal_preview_search_field',
            'sw_product_stream_modal_preview_sales_channel_field_label',
            'sw_product_stream_modal_preview_sales_channel_field',
            'sw_product_stream_modal_preview_grid',
            'sw_product_stream_modal_preview_grid_column_name',
            'sw_product_stream_modal_preview_grid_column_active',
            'sw_product_stream_modal_preview_grid_column_price',
            'sw_product_stream_modal_preview_grid_column_stock',
            'sw_product_stream_modal_preview_grid_pagination',
            'sw_product_stream_modal_preview_empty_state',
            'sw_product_stream_modal_preview_footer',
            'sw_product_stream_modal_preview_close_button',
        ],
        computed: [
            'salesChannelRepository',
            'salesChannelCriteria',
            'previewCriteria',
            'previewSelectionCriteria',
            'productColumns',
            'currencyFilter',
            'stockColorVariantFilter',
        ],
        methods: [
            'createdComponent',
            'onSearchTermChange',
            'onSalesChannelChange',
            'loadEntityData',
            'loadSalesChannels',
            'mapFiltersForSearch',
            'closeModal',
            'getPriceForDefaultCurrency',
            'onPageChange',
            'loadSalesChannelById',
            'isNotEqualToAnyType',
            'addRandomSort',
        ],
    },
});
