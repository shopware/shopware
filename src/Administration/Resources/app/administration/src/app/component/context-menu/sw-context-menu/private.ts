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
    name: 'sw-context-menu',
    load: () => import('./src/sw-context-menu'),
    public: {
        slots: [
            'default',
        ],
        blocks: [
            'sw_context_menu',
            'sw_context_menu_content',
            'sw_context_menu_slot_default',
        ],
    },
});
