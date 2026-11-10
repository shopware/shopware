/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { moveBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.snippet-set-list',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The snippet set list was rebuilt: its header actions moved into the smart bar, and the grid columns, ' +
        'actions and modals have no blocks of their own anymore.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-settings-snippet-set-list'),
    ],
    usage: [
        ...moveBlocks({
            'sw-settings-snippet-set-list': {
                sw_settings_snippet_set_list_card_list: ['sw_settings_snippet_set_list_card_title'],
            },
        }),
        ...moveBlocks(
            {
                'sw-settings-snippet-set-list': {
                    sw_settings_snippet_set_list_card_list: [
                        'sw_settings_snippet_set_list_card_btn_copy',
                        'sw_settings_snippet_set_list_card_btn_copy_icon',
                        'sw_settings_snippet_set_list_card_copy_context_divider',
                        'sw_settings_snippet_set_list_card_copy_context_menu',
                        'sw_settings_snippet_set_list_card_copy_context_menu_items',
                        'sw_settings_snippet_set_list_card_list_btn_copy_split',
                        'sw_settings_snippet_set_list_card_list_container',
                        'sw_settings_snippet_set_list_card_list_container_header',
                        'sw_settings_snippet_set_list_card_list_container_header_btn_add_set',
                        'sw_settings_snippet_set_list_card_list_container_header_btn_edit_set',
                        'sw_settings_snippet_set_list_card_list_container_header_btn_export',
                        'sw_settings_snippet_set_list_card_list_container_header_btn_split',
                    ],
                },
            },
            'The header actions are in the smart-bar-actions slot of the page, which has no block.',
        ),
        ...moveBlocks({
            'sw-settings-snippet-set-list': {
                sw_settings_snippet_set_list_card_list_grid: [
                    'sw_settings_snippet_set_list_card_list_grid_column_base_action',
                    'sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_clone',
                    'sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_delete',
                    'sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_edit',
                    'sw_settings_snippet_set_list_card_list_grid_column_base_action_buttons',
                    'sw_settings_snippet_set_list_card_list_grid_column_base_file',
                    'sw_settings_snippet_set_list_card_list_grid_column_base_file_editor',
                    'sw_settings_snippet_set_list_card_list_grid_column_changed',
                    'sw_settings_snippet_set_list_card_list_grid_column_changed_date',
                    'sw_settings_snippet_set_list_card_list_grid_column_name',
                    'sw_settings_snippet_set_list_card_list_grid_column_name_editor',
                    'sw_settings_snippet_set_list_card_list_grid_column_name_link',
                    'sw_settings_snippet_set_list_card_list_grid_pagination',
                    'sw_settings_snippet_set_list_card_list_grid_pagination_bar',
                    'sw_settings_snippet_set_list_card_list_grid_template',
                    'sw_settings_snippet_set_list_clone_modal_cancel',
                    'sw_settings_snippet_set_list_clone_modal_confirm',
                    'sw_settings_snippet_set_list_clone_modal_confirm_delete_text',
                    'sw_settings_snippet_set_list_clone_modal_footer',
                    'sw_settings_snippet_set_list_columns_clone_modal',
                    'sw_settings_snippet_set_list_columns_delete_modal',
                    'sw_settings_snippet_set_list_delete_modal_cancel',
                    'sw_settings_snippet_set_list_delete_modal_confirm',
                    'sw_settings_snippet_set_list_delete_modal_confirm_delete_text',
                    'sw_settings_snippet_set_list_delete_modal_footer',
                ],
            },
        }),
    ],
});
