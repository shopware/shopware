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
    name: 'sw-promotion-v2-conditions',
    load: () => import('./src/sw-promotion-v2-conditions'),
    public: {
        props: [
            'promotion',
        ],
        blocks: [
            'sw_promotion_v2_conditions',
            'sw_promotion_v2_conditions_pre_conditions_card',
            'sw_promotion_v2_conditions_pre_conditions_sales_channel_selection',
            'sw_promotion_v2_conditions_pre_conditions_prevent_combination',
            'sw_promotion_v2_conditions_pre_conditions_exclusions',
            'sw_promotion_v2_rule_conditions_card',
            'sw_promotion_v2_rule_conditions_rule_select_customer',
            'sw_promotion_v2_rule_conditions_rule_select_cart_conditions',
            'sw_promotion_v2_rule_conditions_rule_select_order_conditions',
        ],
        computed: [
            'exclusionCriteria',
            'personaRuleFilter',
            'cartConditionsRuleFilter',
            'orderConditionsFilter',
        ],
        methods: [
            'createdComponent',
            'loadExclusions',
            'onChangeExclusions',
            'createPromotionCollection',
        ],
    },
});
