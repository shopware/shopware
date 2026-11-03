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
    name: 'sw-cms-el-preview-buy-box',
    load: () => import('./src/sw-cms-el-preview-buy-box'),
    public: {
        blocks: [
            'sw_cms_element_buy_box_preview',
        ],
    },
});
