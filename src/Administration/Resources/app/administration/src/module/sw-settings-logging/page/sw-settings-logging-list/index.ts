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
    name: 'sw-settings-logging-list',
    load: () => import('./src/sw-settings-logging-list'),
    public: {
        blocks: [
            'sw_settings_list',
            'sw_settings_logging_list_search_bar',
            'sw_settings_logging_list_smart_bar_header',
            'sw_settings_logging_list_smart_bar_header_title',
            'sw_settings_logging_list_smart_bar_header_title_text',
            'sw_settings_logging_list_smart_bar_actions',
            'sw_settings_logging_list_content',
            'sw_settings_logging_list_content_listing',
            'sw_settings_logging_list_column_created_at',
            'sw_settings_logging_list_column_level',
            'sw_settings_logging_list_column_context',
            'sw_settings_logging_list_content_listing_pagination',
            'sw_settings_logging_list_content_listing_actions',
            'sw_settings_logging_list_content_listing_action_show_label',
            'sw_settings_logging_list_content_info_modal',
            'sw_settings_logging_list_sidebar',
            'sw_settings_logging_list_sidebar_refresh',
        ],
        computed: [
            'logEntryRepository',
            'logColumns',
            'modalNameFromLogEntry',
            'dateFilter',
        ],
        methods: [
            'showInfoModal',
            'closeInfoModal',
            'getList',
            'logLevelToString',
            'getLogColumns',
        ],
    },
});
