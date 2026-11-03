import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-adding-success',
    load: () => import('./src/sw-extension-adding-success'),
    public: {
        emits: [
            'close',
        ],
        blocks: [
            'sw_extension_adding_success',
            'sw_extension_adding_success_content',
            'sw_extension_adding_success_icon',
            'sw_extension_adding_success_headline',
            'sw_extension_adding_success_sub_headline',
            'sw_extension_adding_success_back_button',
        ],
    },
});
