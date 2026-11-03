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
    name: 'sw-extension-my-extensions-listing-controls',
    load: () => import('./src/sw-extension-my-extensions-listing-controls'),
    public: {
        props: [
            'sortingOption',
        ],
        emits: [
            'update:active-state',
            'update:sorting-option',
        ],
        blocks: [
            'sw_extension_my_extensions_listing_controls',
            'sw_extension_my_extensions_listing_controls_active_switch',
            'sw_extension_my_extensions_listing_controls_sorting_select',
        ],
    },
});
