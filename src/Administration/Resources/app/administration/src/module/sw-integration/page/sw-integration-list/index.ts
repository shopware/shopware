import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-integration-list',
    load: () => import('./src/sw-integration-list'),
    public: {
        blocks: [
            'sw_integration_list',
            'sw_integration_list_smart_bar_header',
            'sw_integration_list_smart_bar_header_title',
            'sw_integration_list_smart_bar_header_title_text',
            'sw_integration_list_smart_bar_actions',
            'sw_integration_list_content',
            'sw_integration_list_introduction',
            'sw_integration_list_overview',
            'sw_integration_list_overview_inner',
            'sw_integration_list_detail_modal',
            'sw_integration_list_detail_modal_inner',
            'sw_integration_list_detail_modal_inner_field_label',
            'sw_integration_list_detail_modal_inner_acl_is_admin',
            'sw_integration_list_detail_modal_inner_acl_roles',
            'sw_integration_list_detail_modal_inner_field_accesskey',
            'sw_integration_list_detail_modal_inner_field_secretaccesskey',
            'sw_integration_list_detail_modal_inner_field_helpText',
            'sw_integration_list_detail_modal_inner_helptext',
            'sw_integration_list_detail_modal_inner_footer',
            'sw_integration_list_detail_modal_inner_footer_cancel',
            'sw_integration_list_detail_modal_inner_footer_apply',
            'sw_integration_list_mcp_modal',
            'sw_integration_list_mcp_modal_allowlist',
            'sw_integration_list_mcp_modal_footer',
            'sw_integration_list_grid',
            'sw_integration_list_grid_inner_slot_columns_label',
            'sw_integration_list_grid_inner_slot_columns_writeAccess',
            'sw_integration_list_grid_inner_slot_columns_actions_edit',
            'sw_integration_list_grid_inner_slot_columns_actions_edit_mcp',
            'sw_integration_list_grid_inner_slot_columns_actions_delete',
            'sw_integration_list_grid_inner_slot_delete_modal',
            'sw_integration_list_grid_inner_slot_delete_modal_confirmtext',
            'sw_integration_list_grid_inner_slot_delete_modal_footer',
            'sw_product_list_empty_state',
        ],
        computed: [
            'integrationRepository',
            'integrationCriteria',
            'mcpGrantedPrivileges',
            'secretAccessKeyFieldTypeIsText',
            'secretAccessKeyFieldTypeIsPassword',
            'integrationColumns',
        ],
        methods: [
            'createdComponent',
            'getList',
            'onSaveIntegration',
            'updateIntegration',
            'createIntegration',
            'createSavedSuccessNotification',
            'createSavedErrorNotification',
            'onGenerateKeys',
            'onShowDetailModal',
            'onCreateIntegration',
            'onCloseDetailModal',
            'onShowMcpModal',
            'onCloseMcpModal',
            'onSaveMcpAllowlist',
            'isAppIntegration',
            'onCloseDeleteModal',
            'onConfirmDelete',
        ],
    },
});
