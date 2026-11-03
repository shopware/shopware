import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
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
