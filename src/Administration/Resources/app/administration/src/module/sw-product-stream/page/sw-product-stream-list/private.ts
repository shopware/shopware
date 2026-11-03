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
    name: 'sw-product-stream-list',
    load: () => import('./src/sw-product-stream-list'),
    public: {
        blocks: [
            'sw_product_stream_list',
            'sw_product_stream_list_search_bar',
            'sw_product_stream_list_smart_bar_header',
            'sw_product_stream_list_smart_bar_header_title',
            'sw_product_stream_list_smart_bar_header_title_text',
            'sw_product_stream_list_smart_bar_header_amount',
            'sw_product_stream_list_smart_bar_actions',
            'sw_product_stream_list_smart_bar_actions_add',
            'sw_manufacturer_list_language_switch',
            'sw_product_stream_list_content',
            'sw_product_stream_list_empty_state',
            'sw_product_stream_list_grid',
            'sw_product_stream_list_grid_bulk',
            'sw_product_stream_list_grid_column_invalid',
            'sw_product_stream_list_grid_colum_updated_at',
            'sw_product_stream_list_grid_more_actions',
            'sw_product_stream_list_grid_duplicate_action',
            'sw_product_stream_list_grid_delete_action',
            'sw_product_stream_list_sidebar',
            'sw_product_stream_list_sidebar_refresh',
        ],
        computed: [
            'productStreamRepository',
            'dateFilter',
        ],
        methods: [
            'onInlineEditSave',
            'onChangeLanguage',
            'getList',
            'getProductStreamColumns',
            'getNoPermissionsTooltip',
            'onDeleteItemFailed',
            'onDeleteItemsFailed',
            'onDuplicate',
        ],
    },
});
