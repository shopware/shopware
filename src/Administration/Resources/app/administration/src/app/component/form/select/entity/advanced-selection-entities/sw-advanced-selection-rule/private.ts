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
    name: 'sw-advanced-selection-rule',
    load: () => import('./src/sw-advanced-selection-rule'),
    public: {
        props: [
            'ruleAwareGroupKey',
            'restrictedRuleIds',
            'restrictedRuleIdsTooltipLabel',
        ],
        emits: [
            'selection-submit',
            'modal-close',
        ],
        blocks: [
            'sw_advanced_selection_rule',
            'sw_advanced_selection_rule__list_grid_columns_name',
            'sw_advanced_selection_rule__list_grid_columns_name_content',
            'sw_advanced_selection_rule__list_grid_columns_priority',
            'sw_advanced_selection_rule__list_grid_columns_priority_content',
            'sw_advanced_selection_rule__list_grid_columns_description',
            'sw_advanced_selection_rule__list_grid_columns_description_content',
            'sw_advanced_selection_rule_list_grid_columns_invalid',
            'sw_advanced_selection_rule_list_grid_columns_invalid_content',
            'sw_advanced_selection_rule_list_grid_columns_create_date',
            'sw_advanced_selection_rule_list_grid_columns_create_date_content',
            'sw_advanced_selection_rule_list_grid_columns_tags',
            'sw_advanced_selection_rule_list_grid_columns_tags_content',
            'sw_advanced_selection_rule_list_grid_columns_assignments',
        ],
        computed: [
            'getRuleDefinition',
            'assignmentProperties',
            'context',
            'columns',
            'filters',
            'conditionFilterOptions',
            'groupFilterOptions',
            'associationFilterOptions',
            'associations',
            'aggregations',
            'dateFilter',
        ],
        methods: [
            'getColumnClass',
            'tooltipConfig',
            'isRestricted',
            'isRecordSelectable',
            'getCounts',
        ],
    },
});
