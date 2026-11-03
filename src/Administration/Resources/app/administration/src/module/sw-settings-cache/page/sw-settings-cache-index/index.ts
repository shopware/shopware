import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-cache-index',
    load: () => import('./src/sw-settings-cache-index'),
    public: {
        blocks: [
            'sw_settings_cache_index',
            'sw_settings_cache_smart_bar_header',
            'sw_settings_cache_smart_bar_header_title',
            'sw_settings_cache_smart_bar_header_title_text',
            'sw_settings_cache_content',
            'sw_settings_cache_content_toolbar',
            'sw_settings_cache_content_toolbar_environment',
            'sw_settings_cache_content_toolbar_environment_heading',
            'sw_settings_cache_content_toolbar_environment_text',
            'sw_settings_cache_content_toolbar_http_cache',
            'sw_settings_cache_content_toolbar_http_cache_heading',
            'sw_settings_cache_content_toolbar_http_cache_text',
            'sw_settings_cache_content_toolbar_cache_adapter',
            'sw_settings_cache_content_toolbar_cache_adapter_heading',
            'sw_settings_cache_content_toolbar_cache_adapter_text',
            'sw_settings_cache_content_clear_data_cache_row',
            'sw_settings_cache_content_clear_data_cache_row_heading',
            'sw_settings_cache_content_clear_data_cache_row_text',
            'sw_settings_cache_content_clear_data_cache_row_button',
            'sw_settings_cache_content_clear_cache_row',
            'sw_settings_cache_content_clear_cache_row_heading',
            'sw_settings_cache_content_clear_cache_row_text',
            'sw_settings_cache_content_clear_cache_row_button',
            'sw_settings_cache_content_indexes_row',
            'sw_settings_cache_content_indexes_row_heading',
            'sw_settings_cache_content_indexes_row_text',
            'sw_settings_cache_content_indexes_row_skip_select',
            'sw_settings_cache_content_indexes_row_button',
        ],
        computed: [
            'httpCacheValue',
            'environmentValue',
            'cacheAdapterValue',
            'indexingMethodOptions',
            'indexers',
            'isIndexSelectionComplete',
        ],
        methods: [
            'createdComponent',
            'resetButtons',
            'decreaseWorkerPoll',
            'clearDataCache',
            'clearCache',
            'updateIndexes',
            'changeSelection',
            'clearIndexerSelection',
            'createOnlySelection',
        ],
    },
});
