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
    name: 'sw-promotion-v2-empty-state-hero',
    load: () => import('./src/sw-promotion-v2-empty-state-hero'),
    public: {
        props: [
            'title',
            'assetPath',
            'description',
            'hideDescription',
        ],
        slots: [
            'actions',
        ],
        blocks: [
            'sw_promotion_v2_empty_state_hero',
            'sw_promotion_v2_empty_state_hero_content',
            'sw_promotion_v2_empty_state_hero_content_title',
            'sw_promotion_v2_empty_state_hero_content_description',
            'sw_promotion_v2_empty_state_hero_content_actions',
            'sw_promotion_v2_empty_state_hero_slot_actions',
            'sw_promotion_v2_empty_state_hero_image',
            'sw_promotion_v2_empty_state_hero_image_asset',
        ],
        computed: [
            'imagePath',
            'showDescription',
            'assetFilter',
            'actionSlotsAvailable',
        ],
    },
});
