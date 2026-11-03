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
    name: 'sw-settings-index',
    load: () => import('./src/sw-settings-index'),
    public: {
        blocks: [
            'sw_settings',
            'sw_settings_content',
            'sw_settings_content_card_view',
            'sw_settings_content_card_view_header',
            'sw_settings_content_card_content_grid',
        ],
        computed: [
            'changeNotices',
            'settingsGroups',
        ],
        methods: [
            'getUserConfig',
            'onCloseSettingRenameBanner',
            'hasPluginConfig',
            'getRouteConfig',
            'getLabel',
            'getGroupLabel',
            'itemIsQueried',
        ],
    },
});
