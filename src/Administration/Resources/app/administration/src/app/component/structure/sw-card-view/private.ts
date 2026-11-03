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
    name: 'sw-card-view',
    load: () => import('./src/sw-card-view'),
    public: {
        props: [
            'showErrorSummary',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_card_view',
            'sw_card_view_content',
            'sw_card_view_slot_default',
        ],
    },
});
