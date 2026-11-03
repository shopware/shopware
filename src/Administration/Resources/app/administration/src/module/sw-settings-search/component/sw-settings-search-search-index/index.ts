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
    name: 'sw-settings-search-search-index',
    load: () => import('./src/sw-settings-search-search-index'),
    public: {
        emits: [
            'edit-change',
        ],
        blocks: [
            'sw_settings_search_search_index',
            'sw_settings_search_search_index_description',
            'sw_settings_search_search_index_warning_top',
            'sw_settings_search_search_index_warning_bottom',
            'sw_settings_search_search_index_rebuild_button',
            'sw_settings_search_search_index_lastest_build',
            'sw_settings_search_search_index_rebuild_progress',
            'sw_settings_search_search_index_rebuild_progress_text',
            'sw_settings_search_search_index_rebuild_progress_bar',
        ],
        computed: [
            'productRepository',
            'productSearchKeywordRepository',
            'productCriteria',
            'productSearchKeywordsCriteria',
        ],
        methods: [
            'createdComponent',
            'beforeDestroyComponent',
            'getLatestProductKeywordIndexed',
            'getTotalProduct',
            'updateProgress',
            'pollData',
            'clearPolling',
            'rebuildSearchIndex',
            'buildFinish',
        ],
    },
});
