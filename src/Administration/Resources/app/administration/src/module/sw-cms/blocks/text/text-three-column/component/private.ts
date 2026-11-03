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
    name: 'sw-cms-block-text-three-column',
    load: () => import('./src/sw-cms-block-text-three-column'),
    public: {
        slots: [
            'left',
            'center',
            'right',
        ],
        blocks: [
            'sw_cms_block_text_three_column',
        ],
    },
});
