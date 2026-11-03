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
    name: 'sw-cms-block-image-text-gallery',
    load: () => import('./src/sw-cms-block-image-text-gallery'),
    public: {
        slots: [
            'left-image',
            'left-text',
            'center-image',
            'center-text',
            'right-image',
            'right-text',
        ],
        blocks: [
            'sw_cms_block_image_text_gallery',
        ],
    },
});
