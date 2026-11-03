import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-store',
    load: () => import('./src/sw-settings-store'),
    public: {
        blocks: [
            'sw_settings_store_index',
            'sw_settings_store_search_bar',
            'sw_settings_store_smart_bar_header',
            'sw_settings_store_smart_bar_header_title',
            'sw_settings_store_smart_bar_header_title_text',
            'sw_settings_store_smart_bar_actions',
            'sw_settings_store_actions_save',
            'sw_settings_store_content',
        ],
        methods: [
            'saveFinish',
            'onSave',
            'trimHost',
            'onLoadingChanged',
        ],
    },
});
