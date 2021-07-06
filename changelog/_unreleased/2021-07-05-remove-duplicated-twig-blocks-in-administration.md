---
title: Remove duplicated twig blocks in administration
issue: NEXT-16002
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: Jannis Leifeld
---
# Administration
* Removed Twig Block duplicates, which should not occur, by renaming the second block and adjusting its content so that the Vue component remains identical as before 
* Changed second twig block `sw_property_assignment_empty_state` to `sw_property_assignment_empty_state_no_options` in `sw-property-assignment`
* Changed second twig block `sw_context_menu_item_icon` to `sw_context_menu_item_entry_icon` in `sw-context-menu-item`
* Changed second twig block `sw_context_menu_item_slot_icon` to `sw_context_menu_item_entry_slot_icon` in `sw-context-menu-item`
* Changed second twig block `sw_context_menu_item_text` to `sw_context_menu_item_entry_text` in `sw-context-menu-item`
* Changed second twig block `sw_context_menu_item_slot_default` to `sw_context_menu_item_entry_slot_default` in `sw-context-menu-item`
* Changed second twig block `sw_data_grid_body_cell_actions` to `sw_data_grid_body_cell_actions_context_button` in `sw-data-grid`
* Changed second twig block `sw_select_rule_create_select_before` to `sw_select_rule_create_single_select_before` in `sw-select-rule-create`
* Changed second twig block `sw_snippet_field_edit_modal_footer` to `sw_snippet_field_edit_modal_footer_button` in `sw-snippet-field-edit-modal`
* Changed second twig block `sw_image_slider_image_container` to `sw_image_slider_image_element_container` in `sw-image-slider`
* Changed second twig block `sw_media_base_item_selection_indicator_icon` to `sw_media_base_item_list_selection_indicator_icon` in `sw-media-base-item`
* Changed second twig block `sw_media_folder_content_folder_icon` to `sw_media_folder_content_folder_button_icon` in `sw-media-folder-content`
* Changed second twig block `sw_media_list_selection_item_v2_placeholder` to `sw_media_list_selection_item_v2_placeholder_icon` in `sw-media-list-selection-item-v2`
* Changed second twig block `sw_media_url_form_input` to `sw_media_url_form_input_inline` in `sw-media-url-form`
* Changed second twig block `sw_condition_value_content` to `sw_condition_value_content_field` in `sw-condition-cart-amount`
* Changed second twig block `sw_admin_menu_item_icon` to `sw_admin_menu_item_external_icon` in `sw-admin-menu-item`
* Changed second twig block `sw_admin_menu_item_text` to `sw_admin_menu_item_external_text` in `sw-admin-menu-item`
* Changed second twig block `sw_admin_menu_item_arrow_indicato` to `sw_admin_menu_item_external_arrow_indicato` in `sw-admin-menu-item`
* Changed second twig block `sw_admin_menu_item_text` to `sw_admin_menu_item_navigation_text` in `sw-admin-menu-item`
* Changed second twig block `sw_admin_menu_item_arrow_indicator` to `sw_admin_menu_item_external_arrow_indicator` in `sw-admin-menu-item`
* Changed second twig block `sw_search_bar_item_cms_page_label` to `sw_search_bar_item_cms_landing_page_label` in `sw-seach-bar-item`
* Changed second twig block `sw_tree_items_active_state` to `sw_tree_items_transition_active_state` in `sw-tree-item`
* Changed second twig block `sw_tree_items_actions_without_position` to `sw_tree_items_transition_actions_without_position` in `sw-tree-item`
* Changed second twig block `sw_tree_items_actions_before` to `sw_tree_items_transition_actions_before` in `sw-tree-item`
* Changed second twig block `sw_tree_items_actions_after` to `sw_tree_items_transition_actions_after` in `sw-tree-item`
* Changed second twig block `sw_tree_items_actions_sub` to `sw_tree_items_transition_actions_sub` in `sw-tree-item`
* Changed second twig block `sw_tree_items_actions_duplicate` to `sw_tree_items_transition_actions_duplicate` in `sw-tree-item`
* Changed second twig block `sw_tree_items_actions_group` to `sw_tree_items_transition_actions_group` in `sw-tree-item`
* Changed second twig block `sw_tree_items_actions_edit` to `sw_tree_items_transition_actions_edit` in `sw-tree-item`
* Changed second twig block `sw_tree_items_actions_delete` to `sw_tree_items_transition_actions_delete` in `sw-tree-item`
* Changed second twig block `sw_cms_layout_modal_content` to `sw_cms_layout_modal_content_container` in `sw-cms-layout-modal`
* Changed second twig block `sw_cms_section_content_block_slot` to `sw_cms_section_content_block_component_slot` in `sw-cms-section`
* Changed second twig block `sw_customer_detail_addresses` to `sw_customer_detail_addresses_card` in `sw-customer-detail-addresses`
* Changed second twig block `sw_bought_extension_card_installation_failed_modal` to `sw_bought_extension_card_installation_failed_modal_extension` in `sw-extension-card-bought`
* Changed second twig block `sw_mail_template_detail_sidebar` to `sw_mail_template_detail_sidebar_inner` in `sw-mail-template-detail`
* Changed second twig block `sw_order_line_items_grid_create_actions_dropdown` to `sw_order_line_items_grid_create_actions_dropdown_menu_item` in `sw-order-line-items-grid`
* Changed second twig block `sw_product_stream_detail_filter` to `sw_product_stream_detail_filter_tree` in `sw-product-stream-detail`
* Changed second twig block `sw_sales_channel_detail_base_general_input_product_comparison_storefront` to `sw_sales_channel_detail_base_general_input_product_comparison_storefront_select` in `sw-sales-channel-detail-base`
* Changed second twig block `sw_settings_country_list_grid` to `sw_settings_country_list_grid_inner` in `sw-settings-country-list`
* Changed second twig block `sw_settings_document_list_columns_name_link` to `sw_settings_document_list_columns_name_link_inner` in `sw-settings-document-list`
* Changed second twig block `sw_settings_number_range_list_grid` to `sw_settings_number_range_list_grid_inner` in `sw-settings-number-range-list`
* Changed second twig block `sw_settings_payment_list_content` to `sw_settings_payment_list_content_inner` in `sw-settings-payment-list`
* Changed second twig block `sw_settings_search_searchable_show_example_link` to `sw_settings_search_searchable_show_example_link_element` in `sw-settings-search-searchable-content`

