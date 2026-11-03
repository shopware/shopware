import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-basic-information',
    load: () => import('./src/sw-settings-basic-information'),
    public: {
        blocks: [
            'sw_settings_basic_information_index',
            'sw_settings_basic_information_search_bar',
            'sw_settings_basic_information_smart_bar_header',
            'sw_settings_basic_information_smart_bar_header_title',
            'sw_settings_basic_information_smart_bar_header_title_text',
            'sw_settings_basic_information_smart_bar_header_amount',
            'sw_settings_basic_information_smart_bar_actions',
            'sw_settings_basic_information_actions_save',
            'sw_settings_basic_information_content',
        ],
        methods: [
            'saveFinish',
            'onSave',
            'onLoadingChanged',
        ],
    },
});
