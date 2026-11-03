import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-country-preview-template',
    load: () => import('./src/sw-settings-country-preview-template'),
    public: {
        props: [
            'formattingAddress',
            'isLoading',
        ],
        blocks: [
            'sw_settings_country_preview_template',
        ],
        computed: [
            'displayFormattingAddress',
        ],
    },
});
