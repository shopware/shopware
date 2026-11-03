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
    name: 'sw-sales-channel-list',
    load: () => import('./src/sw-sales-channel-list'),
    public: {
        blocks: [
            'sw_sales_channel_list',
            'sw_sales_channel_list_search_bar',
            'sw_sales_channel_list_smart_bar_header',
            'sw_sales_channel_list_smart_bar_header_title',
            'sw_sales_channel_list_smart_bar_header_title_text',
            'sw_sales_channel_list_smart_bar_header_amount',
            'sw_sales_channel_list_actions',
            'sw_sales_channel_list_smart_bar_actions',
            'sw_sales_channel_list_content',
            'sw_sales_channel_list_content_card',
            'sw_sales_channel_list_grid',
            'sw_sales_channel_list_grid_column_name',
            'sw_sales_channel_list_grid_column_status',
            'sw_sales_channel_list_grid_column_created_at',
            'sw_sales_channel_list_grid_column_favorite',
            'sw_sales_channel_list_grid_actions',
            'sw_sales_channel_list_grid_actions_item_detail',
            'sw_sales_channel_list_grid_actions_item_open_storefront',
            'sw_sales_channel_list_empty_state',
            'sw_sales_channel_list_empty_state_no_sales_channels',
        ],
        computed: [
            'salesChannelColumns',
            'salesChannelRepository',
            'salesChannelCriteria',
            'salesChannelFavoritesService',
            'dateFilter',
        ],
        methods: [
            'onAddSalesChannel',
            'getList',
            'checkForDomainLink',
            'openStorefrontLink',
            'isFavorite',
            'isStorefrontSalesChannel',
        ],
    },
});
