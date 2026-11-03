import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package innovation
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-media',
    load: () => import('./src/sw-settings-media'),
    public: {
        blocks: [
            'sw_settings_media_index',
            'sw_settings_media_header',
            'sw_settings_media_smart_bar_actions',
            'sw_settings_media_actions_save',
            'sw_settings_media_content',
        ],
        methods: [
            'createdComponent',
            'saveFinish',
            'onSave',
            'onLoadingChanged',
        ],
    },
});
