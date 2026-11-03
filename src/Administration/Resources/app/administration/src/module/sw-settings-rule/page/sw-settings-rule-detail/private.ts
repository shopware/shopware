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
