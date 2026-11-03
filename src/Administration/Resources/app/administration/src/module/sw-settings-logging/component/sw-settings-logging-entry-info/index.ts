import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The modal that shows the raw context of a log entry in the log list; the mail log modal extends it.
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-logging-entry-info',
    load: () => import('./src/sw-settings-logging-entry-info'),
    public: {
        props: [
            'logEntry',
        ],
        emits: [
            'close',
        ],
        blocks: [
            'sw_settings_logging_entry_info',
            'sw_settings_logging_entry_info_tabs',
            'sw_settings_logging_entry_info_mt_tabs',
            'sw_settings_logging_entry_info_mt_content',
            'sw_settings_logging_entry_info_mt_raw_content',
            'sw_settings_logging_entry_info_tab_items',
            'sw_settings_logging_entry_info_content',
            'sw_settings_logging_entry_info_raw_content',
            'sw_settings_logging_entry_info_footer',
            'sw_settings_logging_entry_info_close_button',
        ],
        computed: [
            'entryInfoTabs',
            'displayString',
        ],
        methods: [
            'onClose',
        ],
    },
});
