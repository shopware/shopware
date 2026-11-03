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
    name: 'sw-promotion-v2-settings-trigger',
    load: () => import('./src/sw-promotion-v2-settings-trigger'),
    public: {
        props: [
            'discount',
        ],
        blocks: [
            'sw_promotion_v2_settings_trigger',
            'sw_promotion_v2_settings_trigger_description',
            'sw_promotion_v2_settings_trigger_radio_switch',
            'sw_promotion_v2_settings_trigger_container',
            'sw_promotion_v2_settings_trigger_select_trigger_type',
            'sw_promotion_v2_settings_trigger_rule_selection_selection',
        ],
        computed: [
            'ruleCriteria',
            'triggerOptions',
        ],
        methods: [
            'getTriggerSelection',
        ],
    },
});
