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
    name: 'sw-settings-rule-list',
    load: () => import('./src/sw-settings-rule-list'),
    public: {
        blocks: [
            'sw_settings_list',
            'sw_settings_rule_index',
            'sw_settings_rule_list_search_bar',
            'sw_settings_rule_list_smart_bar_header',
            'sw_settings_rule_list_smart_bar_header_title',
            'sw_settings_rule_list_smart_bar_header_title_text',
            'sw_settings_rule_list_smart_bar_header_title_amount',
            'sw_settings_rule_list_smart_bar_actions',
            'sw_settings_rule_list_smart_bar_actions_add',
            'sw_settings_rule_list_content',
            'sw_settings_rule_list_content_card',
            'sw_settings_rule_list_grid',
            'sw_settings_rule_list_grid_column_tags',
            'sw_settings_rule_list_grid_columns_actions',
            'sw_settings_rule_list_grid_columns_actions_duplicate',
            'sw_settings_rule_list_empty_message',
            'sw_settings_rule_list_sidebar',
            'sw_settings_rule_list_sidebar_refresh',
            'sw_settings_rule_list_sidebar_filter',
        ],
        computed: [
            'getRuleDefinition',
            'hasActiveSearchOrFilter',
            'ruleRepository',
            'conditionFilterOptions',
            'groupFilterOptions',
            'associationFilterOptions',
            'listFilters',
            'listCriteria',
            'assignmentProperties',
            'dateFilter',
        ],
        methods: [
            'getList',
            'onChangeLanguage',
            'onDuplicate',
            'onInlineEditSave',
            'updateCriteria',
            'getRuleColumns',
        ],
    },
});
