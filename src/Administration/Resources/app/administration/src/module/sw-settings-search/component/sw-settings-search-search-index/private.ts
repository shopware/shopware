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
