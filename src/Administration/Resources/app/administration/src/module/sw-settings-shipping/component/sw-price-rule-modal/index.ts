import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-rule-modal,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-price-rule-modal',
    extends: 'sw-rule-modal',
    load: () => import('./src/sw-price-rule-modal'),
    public: {
        props: [
            'allowedRuleScopes',
            'ruleAwareGroupKey',
        ],
        emits: [
            'save',
            'modal-close',
        ],
        blocks: [
            'sw_rule_modal_basic_info_description',
            'sw_rule_modal',
            'sw_rule_modal_basic_info',
            'sw_rule_modal_basic_info_name',
            'sw_rule_modal_basic_info_priority',
            'sw_rule_modal_condition_tree',
            'sw_rule_modal_footer',
            'sw_rule_modal_footer_button_abort',
            'sw_rule_modal_footer_button_save',
        ],
        computed: [
            'modalTitle',
            'ruleRepository',
            'ruleConditionRepository',
            'appScriptConditionRepository',
        ],
        methods: [
            'createdComponent',
            'loadConditionData',
            'conditionsChanged',
            'getChildrenConditions',
            'validateRuleAwareness',
            'saveAndClose',
        ],
    },
});
