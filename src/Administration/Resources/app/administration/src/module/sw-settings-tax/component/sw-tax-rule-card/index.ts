import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-tax-rule-card',
    load: () => import('./src/sw-tax-rule-card'),
    public: {
        props: [
            'tax',
            'isLoading',
            'disabled',
        ],
        blocks: [
            'sw_tax_rule_card',
            'sw_tax_rule_card_header',
            'sw_tax_rule_card_header_filter',
            'sw_tax_rule_card_header_create_rule_button',
            'sw_tax_rule_card_header_create_document_button_label',
            'sw_tax_rule_card_grid',
            'sw_settings_tax_rule_grid_column_tax_rate',
            'sw_settings_tax_rule_grid_column_tax_rule_type_type_name',
            'sw_settings_tax_rule_grid_column_tax_rule_active_from',
            'sw_settings_tax_list_grid_columns_actions',
            'sw_settings_tax_list_grid_columns_actions_edit',
            'sw_settings_tax_list_grid_columns_actions_delete',
            'sw_settings_tax_list_grid_action_modals',
            'sw_settings_tax_list_delete_modal',
            'sw_settings_tax_list_delete_modal_confirm_delete_text',
            'sw_settings_tax_list_delete_modal_footer',
            'sw_settings_tax_list_delete_modal_cancel',
            'sw_settings_tax_list_delete_modal_confirm',
            'sw_settings_tax_list_grid_pagination',
            'sw_tax_rule_card_grid_column_modal',
            'sw_tax_rule_card_empty_state',
            'sw_tax_rule_card_empty_state_button',
            'sw_tax_rule_card_empty_state_image',
            'sw_tax_rule_card_empty_state_label',
        ],
        computed: [
            'taxRuleRepository',
            'taxRulesEmpty',
            'taxRuleCardClasses',
            'taxRuleCriteria',
            'getColumns',
            'assetFilter',
            'dateFilter',
        ],
        methods: [
            'createdComponent',
            'getList',
            'paginate',
            'onColumnSort',
            'onSearchTermChange',
            'onModalClose',
            'showRuleModal',
            'onDelete',
            'onCloseDeleteModal',
            'onConfirmDelete',
            'hasTypeCellComponent',
            'getTypeCellComponent',
        ],
    },
});
