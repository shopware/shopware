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
    name: 'sw-promotion-v2-cart-condition-form',
    load: () => import('./src/sw-promotion-v2-cart-condition-form'),
    public: {
        props: [
            'promotion',
            'restrictedRules',
        ],
        blocks: [
            'sw_promotion_v2_cart_condition_form',
            'sw_promotion_v2_cart_condition_form_rules_field',
            'sw_promotion_v2_cart_condition_form_use_setgroups_field',
            'sw_promotion_v2_cart_condition_setgroup_card',
            'sw_promotion_v2_cart_condition_setgroup_card_context_action',
            'sw_promotion_v2_cart_condition_setgroup_card_context_action_duplicate',
            'sw_promotion_v2_cart_condition_setgroup_card_context_action_delete',
            'sw_promotion_v2_cart_condition_setgroup_card_title',
            'sw_promotion_v2_cart_condition_setgroup_mode_field',
            'sw_promotion_v2_cart_condition_setgroup_value_field',
            'sw_promotion_v2_cart_condition_setgroup_sorting_field',
            'sw_promotion_v2_cart_condition_setgroup_rules_field',
            'sw_promotion_v2_cart_condition_add_group_button',
        ],
        computed: [
            'promotionGroupRepository',
            'ruleFilter',
            'packagers',
            'sorters',
            'isEditingDisabled',
            'packagerOptions',
            'sorterOptions',
            'setGroupCriteria',
        ],
        methods: [
            'createdComponent',
            'addSetGroup',
            'duplicateSetGroup',
            'deleteSetGroup',
        ],
    },
});
