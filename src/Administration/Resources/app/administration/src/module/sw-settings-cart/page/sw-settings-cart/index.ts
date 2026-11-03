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
    name: 'sw-settings-cart',
    load: () => import('./src/sw-settings-cart'),
    public: {
        blocks: [
            'sw_settings_cart_index',
            'sw_settings_cart_search_bar',
            'sw_settings_cart_smart_bar_header',
            'sw_settings_cart_smart_bar_header_title',
            'sw_settings_cart_smart_bar_header_title_text',
            'sw_settings_cart_smart_bar_header_amount',
            'sw_settings_cart_smart_bar_actions',
            'sw_settings_cart_actions_save',
            'sw_settings_cart_content',
        ],
        methods: [
            'saveFinish',
            'onSave',
            'onLoadingChanged',
        ],
    },
});
