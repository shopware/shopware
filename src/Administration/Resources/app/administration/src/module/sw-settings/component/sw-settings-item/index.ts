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
    name: 'sw-settings-item',
    load: () => import('./src/sw-settings-item'),
    public: {
        props: [
            'label',
            'to',
            'disabled',
        ],
        slots: [
            'icon',
            'label',
        ],
        blocks: [
            'sw_settings_item',
            'sw_settings_item_icon',
            'sw_settings_item_label',
        ],
        computed: [
            'classes',
        ],
    },
});
