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
    name: 'sw-promotion-v2-settings-rule-selection',
    load: () => import('./src/sw-promotion-v2-settings-rule-selection'),
    public: {
        props: [
            'discount',
        ],
        blocks: [
            'sw_promotion_v2_settings_rule_selection',
            'sw_promotion_v2_settings_rule_selection_description',
            'sw_promotion_v2_settings_rule_selection_selection',
        ],
        computed: [
            'ruleCriteria',
        ],
    },
});
