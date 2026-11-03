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
    name: 'sw-settings-rule-detail-base',
    load: () => import('./src/sw-settings-rule-detail-base'),
    public: {
        props: [
            'rule',
            'conditions',
            'conditionRepository',
            'isLoading',
            'ruleNameError',
            'rulePriorityError',
        ],
        emits: [
            'conditions-changed',
            'tree-finished-loading',
        ],
        blocks: [
            'sw_settings_rule_detail_base',
            'sw_settings_rule_detail_base_content_card',
            'sw_settings_rule_detail_base_content_card_field_name',
            'sw_settings_rule_detail_base_content_card_field_priority',
            'sw_settings_rule_detail_base_content_card_field_description',
            'sw_settings_rule_detail_base_content_card_field_type',
            'sw_settings_rule_detail_base_content_card_field_tags',
            'sw_settings_rule_detail_base_conditions_card',
            'sw_settings_rule_detail_custom_field_sets',
        ],
        computed: [
            'availableModuleTypes',
            'moduleTypes',
            'showCustomFields',
            'productStreamIndexingEnabled',
            'showProductStreamIndexingWarning',
            'deprecatedConditionsInUse',
            'flowOnlyConditionLabels',
        ],
        methods: [
            'createdComponent',
            'loadCustomFieldSets',
            'hasProductStreamConditions',
        ],
    },
});
