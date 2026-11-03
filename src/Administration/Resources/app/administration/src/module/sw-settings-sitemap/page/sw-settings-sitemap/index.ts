import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-sitemap',
    load: () => import('./src/sw-settings-sitemap'),
    public: {
        blocks: [
            'sw_settings_sitemap_index',
            'sw_settings_sitemap_search_bar',
            'sw_settings_sitemap_smart_bar_header',
            'sw_settings_sitemap_smart_bar_header_title',
            'sw_settings_sitemap_smart_bar_header_title_text',
            'sw_settings_sitemap_smart_bar_actions',
            'sw_settings_sitemap_actions_save',
            'sw_settings_sitemap_content',
        ],
        methods: [
            'saveFinish',
            'onSave',
            'onLoadingChanged',
        ],
    },
});
