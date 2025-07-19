---
title: Refactored snippet set list
issue: #9532
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Administration
* Changed `sw-snipept-set-list` to use `sw-entity-listing` instead of `sw-grid` to improve behaviour
* Deprecated several methods in `sw-snippet-set-list/index.js`, which will be removed without replacement:
  * onClone
  * closeCloneModal
  * getNoPermissionsTooltip
* Deprecated position identifier `sw-settings-snippet-set-list-title`, which will be removed without replacement
* Deprecated several blocks in `sw-snippet-set-list.twig`, which will be removed without replacement:
  * sw_settings_snippet_set_list_card_title 
  * sw_settings_snippet_set_list_card_list_container
  * sw_settings_snippet_set_list_card_list_container_header
  * sw_settings_snippet_set_list_card_list_container_header_btn_export
  * sw_settings_snippet_set_list_card_list_container_header_btn_edit_set
  * sw_settings_snippet_set_list_card_list_container_header_btn_split
  * sw_settings_snippet_set_list_card_list_container_header_btn_add_set
  * sw_settings_snippet_set_list_card_btn_copy_icon
  * sw_settings_snippet_set_list_card_list_btn_copy_split
  * sw_settings_snippet_set_list_card_btn_copy
  * sw_settings_snippet_set_list_card_copy_context_menu
  * sw_settings_snippet_set_list_card_copy_context_divider
  * sw_settings_snippet_set_list_card_copy_context_menu_items
  * sw_settings_snippet_set_list_card_list_grid_template
  * sw_settings_snippet_set_list_card_list_grid_column_name
  * sw_settings_snippet_set_list_card_list_grid_column_name_link
  * sw_settings_snippet_set_list_card_list_grid_column_name_editor
  * sw_settings_snippet_set_list_card_list_grid_column_changed
  * sw_settings_snippet_set_list_card_list_grid_column_changed_date
  * sw_settings_snippet_set_list_card_list_grid_column_base_file
  * sw_settings_snippet_set_list_card_list_grid_column_base_file_editor
  * sw_settings_snippet_set_list_card_list_grid_column_base_action
  * sw_settings_snippet_set_list_card_list_grid_column_base_action_buttons
  * sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_edit
  * sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_clone
  * sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_delete
  * sw_settings_snippet_set_list_columns_delete_modal
  * sw_settings_snippet_set_list_delete_modal_confirm_delete_text
  * sw_settings_snippet_set_list_delete_modal_footer
  * sw_settings_snippet_set_list_delete_modal_cancel
  * sw_settings_snippet_set_list_delete_modal_confirm
  * sw_settings_snippet_set_list_columns_clone_modal
  * sw_settings_snippet_set_list_clone_modal_confirm_delete_text
  * sw_settings_snippet_set_list_clone_modal_footer
  * sw_settings_snippet_set_list_clone_modal_cancel
  * sw_settings_snippet_set_list_clone_modal_confirm
  * sw_settings_snippet_set_list_card_list_grid_pagination
  * sw_settings_snippet_set_list_card_list_grid_pagination_bar
