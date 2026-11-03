import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-rule-modal',
    load: () => import('./src/sw-flow-rule-modal'),
    public: {
        props: [
            'ruleId',
        ],
        emits: [
            'process-finish',
            'modal-close',
        ],
        blocks: [
            'sw_flow_rule_modal',
            'sw_flow_rule_modal_tabs',
            'sw_flow_rule_modal_mt_tabs',
            'sw_flow_rule_modal_mt_content',
            'sw_flow_rule_modal_mt_tab_detail_content',
            'sw_flow_rule_modal_mt_detail_name',
            'sw_flow_rule_modal_mt_detail_priority',
            'sw_flow_rule_modal_mt_detail_description',
            'sw_flow_rule_modal_mt_detail_type',
            'sw_flow_rule_modal_mt_tab_rule_content',
            'sw_flow_rule_modal_mt_conditions_card',
            'sw_flow_rule_headers',
            'sw_flow_rule_modal_tab_detail',
            'sw_flow_rule_modal_tab_rule',
            'sw_flow_rule_modal_content',
            'sw_flow_rule_modal_tab_detail_content',
            'sw_flow_rule_modal_detail_name',
            'sw_flow_rule_modal_detail_priority',
            'sw_flow_rule_modal_detail_description',
            'sw_flow_rule_modal_detail_type',
            'sw_flow_rule_modal_tab_rule_content',
            'sw_flow_rule_modal_conditions_card',
            'sw_flow_rule_modal_footer_cancel_button',
            'sw_flow_rule_modal_footer_save_button',
        ],
        computed: [
            'modalTitle',
            'ruleRepository',
            'conditionRepository',
            'appScriptConditionRepository',
            'availableModuleTypes',
            'tabs',
            'moduleTypes',
            'scopesOfRuleAwarenessKey',
            'deprecatedConditionsInUse',
        ],
        methods: [
            'createdComponent',
            'loadConditionData',
            'createRule',
            'loadRule',
            'loadConditions',
            'syncConditions',
            'onConditionsChanged',
            'getRuleDetail',
            'onSaveRule',
            'saveRule',
            'showErrorNotification',
            'onClose',
            'createConditionCriteria',
        ],
    },
});
