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
    name: 'sw-settings-rule-detail',
    load: () => import('./src/sw-settings-rule-detail'),
    public: {
        props: [
            'ruleId',
        ],
        blocks: [
            'sw_settings_rule_detail',
            'sw_settings_rule_detail_header',
            'sw_settings_rule_detail_actions',
            'sw_settings_rule_detail_actions_cancel',
            'sw_settings_rule_detail_actions_save',
            'sw_settings_rule_detail_actions_save_context_menu',
            'sw_settings_rule_detail_actions_save_context_menu_actions',
            'sw_settings_rule_detail_actions_duplicate',
            'sw_settings_rule_detail_content',
            'sw_settings_rule_detail_tabs_discard_changes_modal',
            'sw_settings_rule_detail_tabs',
            'sw_settings_rule_detail_mt_tabs',
            'sw_settings_rule_detail_tab_items',
            'sw_settings_rule_detail_content_view',
        ],
        computed: [
            'identifier',
            'ruleRepository',
            'ruleCriteria',
            'appScriptConditionRepository',
            'conditionRepository',
            'tooltipSave',
            'tooltipCancel',
            'tabItems',
            'ruleDetailTabs',
            'conditionTreeFlat',
        ],
        methods: [
            'createRuleCriteria',
            'loadConditionData',
            'createRule',
            'loadEntityData',
            'extractEntityCount',
            'unsavedDataLeaveHandler',
            'checkUnsavedData',
            'setTreeFinishedLoading',
            'onLeaveModalClose',
            'onLeaveModalConfirm',
            'loadConditions',
            'conditionsChanged',
            'validateRuleAwareness',
            'getChildrenConditions',
            'validateDateRange',
            'invalidDateRangeConditions',
            'onSave',
            'saveRuleChanges',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
            'saveRule',
            'syncConditions',
            'showErrorNotification',
            'tabHasError',
            'onCancel',
            'onDuplicate',
            'createConditionCriteria',
        ],
    },
});
