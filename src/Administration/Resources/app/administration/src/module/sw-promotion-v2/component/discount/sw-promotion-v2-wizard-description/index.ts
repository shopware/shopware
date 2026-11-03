import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-promotion-v2-wizard-description',
    load: () => import('./src/sw-promotion-v2-wizard-description'),
    public: {
        slots: [
            'default',
        ],
        blocks: [
            'sw_promotion_v2_wizard_description',
            'sw_promotion_v2_wizard_description_slot_default',
        ],
    },
});
