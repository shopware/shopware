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
    name: 'sw-cms-block-video',
    load: () => import('./src/sw-cms-block-video'),
    public: {
        slots: [
            'video',
        ],
        blocks: [
            'sw_cms_block_video',
            'sw_cms_block_video_slot_video',
        ],
    },
});
