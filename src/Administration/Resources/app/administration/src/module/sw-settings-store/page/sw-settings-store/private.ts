import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
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
