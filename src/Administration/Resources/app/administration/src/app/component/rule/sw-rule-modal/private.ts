import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-rule-modal',
    load: () => import('./src/sw-rule-modal'),
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
            'sw_rule_modal',
            'sw_rule_modal_basic_info',
            'sw_rule_modal_basic_info_name',
            'sw_rule_modal_basic_info_priority',
            'sw_rule_modal_basic_info_description',
            'sw_rule_modal_condition_tree',
            'sw_rule_modal_footer',
            'sw_rule_modal_footer_button_abort',
            'sw_rule_modal_footer_button_save',
        ],
        computed: [
            'ruleRepository',
            'ruleConditionRepository',
            'appScriptConditionRepository',
            'modalTitle',
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
