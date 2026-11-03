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
    name: 'sw-settings-seo',
    load: () => import('./src/sw-settings-seo'),
    public: {
        blocks: [
            'sw_settings_seo',
            'sw_settings_seo_smart_bar_header',
            'sw_settings_seo_smart_bar_header_title',
            'sw_settings_seo_smart_bar_header_title_text',
            'sw_settings_seo_smart_bar_actions',
            'sw_settings_seo_smart_bar_actions_add',
            'sw_settings_seo_content',
            'sw_settings_seo_content_inner',
            'sw_settings_seo_content_inner_seo_url_template',
            'sw_settings_seo_content_inner_redirect',
        ],
        methods: [
            'onClickSave',
            'onLoadingChanged',
            'onSalesChannelChanged',
        ],
    },
});
