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
    name: 'sw-settings-search-live-search',
    load: () => import('./src/sw-settings-search-live-search'),
    public: {
        props: [
            'currentSalesChannelId',
            'searchTerms',
            'searchResults',
        ],
        emits: [
            'live-search-results-change',
            'sales-channel-change',
        ],
        blocks: [
            'sw_settings_search_view_live_search_card',
            'sw_settings_search_view_live_search_rebuild_index_row',
            'sw_settings_search_view_live_search_description',
            'sw_settings_search_view_live_search_show_example_link',
            'sw_settings_search_searchable_show_example_modal',
            'sw_settings_search_view_live_search_sales_channel',
            'sw_settings_search_view_live_search_input',
            'sw_settings_search_view_live_search_search_icon_wrapper',
            'sw_settings_search_view_live_search_search_icon',
            'sw_settings_search_view_live_search_results',
            'sw_search_bar_results_empty_state',
            'sw_settings_search_view_live_search_results_search_grid',
            'sw_settings_search_view_live_search_results_search_grid_columns',
            'sw_settings_search_view_live_search_results_search_grid_name',
            'sw_settings_search_view_live_search_results_search_grid_score',
            'sw_settings_search_view_live_search_results_no_result',
        ],
        computed: [
            'salesChannelRepository',
            'productSortingRepository',
            'isSearchEnable',
            'searchColumns',
            'products',
            'productSortingCriteria',
            'searchParams',
        ],
        methods: [
            'createdComponent',
            'searchOnStorefront',
            'fetchSalesChannels',
            'fetchProductSortings',
            'changeSalesChannel',
            'onShowExampleModal',
            'onCloseExampleModal',
        ],
    },
});
