/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-snippet-set-list',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-snippet-set-list that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_btn_copy',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_btn_copy_icon',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_copy_context_divider',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_copy_context_menu',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_copy_context_menu_items',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_btn_copy_split',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_container',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_container_header',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_container_header_btn_add_set',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_container_header_btn_edit_set',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_container_header_btn_export',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_container_header_btn_split',
            moveTo: 'sw_settings_snippet_set_list_card_list (the header actions are in the smart-bar-actions slot, which has no block)',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_base_action',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_clone',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_delete',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_edit',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_base_action_buttons',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_base_file',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_base_file_editor',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_changed',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_changed_date',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_name',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_name_editor',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_column_name_link',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_pagination',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_pagination_bar',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_list_grid_template',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_card_title',
            moveTo: 'sw_settings_snippet_set_list_card_list',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_clone_modal_cancel',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_clone_modal_confirm',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_clone_modal_confirm_delete_text',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_clone_modal_footer',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_columns_clone_modal',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_columns_delete_modal',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_delete_modal_cancel',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_delete_modal_confirm',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_delete_modal_confirm_delete_text',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_snippet_set_list_delete_modal_footer',
            moveTo: 'sw_settings_snippet_set_list_card_list_grid',
            components: ['sw-settings-snippet-set-list'],
        }),
    ],
});
