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
    name: 'sw-select-rule-create',
    load: () => import('./src/sw-select-rule-create'),
    public: {
        props: [
            'ruleId',
            'rules',
            'ruleFilter',
            'ruleAwareGroupKey',
            'restrictedRuleIds',
            'restrictedRuleIdsTooltipLabel',
            'size',
        ],
        emits: [
            'save-rule',
            'dismiss-rule',
            'update:rules',
        ],
        slots: [
            'result-item',
            'result-label-property',
            'rule-modal',
        ],
        blocks: [
            'sw_select_rule_create',
            'sw_select_rule_create_select_before',
            'sw_select_rule_create_select_multi_select_result_list_item',
            'sw_select_rule_create_select_multi_select_result_item_label',
            'sw_select_rule_create_select',
            'sw_select_rule_create_single_select_before',
            'sw_select_rule_create_select_entity_select_result_list_item',
            'sw_select_rule_create_select_entity_select_result_item_label',
            'sw_select_rule_create_rule_modal',
            'sw_select_rule_create_rule_modal_inner',
        ],
        computed: [
            'collection',
        ],
        methods: [
            'onSaveRule',
            'onSelectRule',
            'onUpdateCollection',
            'openCreateRuleModal',
            'onCloseRuleModal',
            'onRuleSelectInput',
            'isRuleRestricted',
            'getAdvancedSelectionParameters',
            'tooltipConfig',
        ],
    },
});
