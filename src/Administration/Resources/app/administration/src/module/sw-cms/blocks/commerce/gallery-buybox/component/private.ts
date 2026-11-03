import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-cms-block-gallery-buybox',
    load: () => import('./src/sw-cms-block-gallery-buybox'),
    public: {
        slots: [
            'left',
            'right',
        ],
        blocks: [
            'sw_cms_block_gallery_buybox',
            'sw_cms_block_gallery_buybox_slot_left',
            'sw_cms_block_gallery_buybox_slot_right',
        ],
        computed: [
            'currentDeviceView',
            'currentDeviceViewClass',
        ],
    },
});
