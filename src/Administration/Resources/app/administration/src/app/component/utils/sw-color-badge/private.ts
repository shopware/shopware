import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-color-badge',
    load: () => import('./src/sw-color-badge'),
    public: {
        props: [
            'variant',
            'color',
            'rounded',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_color_badge',
            'sw_color_badfge_slot_default',
        ],
        computed: [
            'colorStyle',
            'variantClass',
        ],
    },
});
