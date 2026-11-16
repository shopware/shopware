/**
 * @sw-package framework
 *
 * Twig blocks that 6.8 removes by a `@deprecated tag:v6.8.0` marker or with their whole component, per core component.
 */

import { deprecated, removedComponent } from './removed-blocks';
import type { RemovedBlocks } from './removed-blocks';

export default [
    deprecated('sw-bulk-edit-product', {
        sw_bulk_edit_product_content_meansures_packaging_card: {
            hint: 'Override sw_bulk_edit_product_content_measurement_card or sw_bulk_edit_product_content_selling_packaging_card instead.',
        },
    }),
    deprecated('sw-flow-list', {
        sw_flow_list_empty_state_icon: { extend: 'sw_flow_list_empty_state' },
    }),
    deprecated('sw-import-export', {
        sw_import_export_language_switch: null,
    }),
    deprecated('sw-import-export-edit-profile-general', {
        sw_import_export_edit_profile_general_container_name: null,
    }),
    deprecated('sw-import-export-new-profile-wizard-csv-page', {
        sw_import_export_new_profile_wizard_csv_page_text: {
            extend: 'sw_import_export_new_profile_wizard_csv_page_csv_upload',
        },
    }),
    deprecated('sw-import-export-view-export', {
        sw_import_export_view_export_activity_title: { extend: 'sw_import_export_view_export_activity' },
    }),
    deprecated('sw-import-export-view-import', {
        sw_import_export_view_import_activity_title: { extend: 'sw_import_export_view_import_activity' },
    }),
    deprecated('sw-import-export-view-profiles', {
        sw_import_export_view_profile_profiles_listing_column_label: {
            extend: 'sw_import_export_view_profile_profiles_listing',
        },
    }),
    deprecated('sw-integration-list', {
        sw_integration_list_introduction: null,
    }),
    deprecated('sw-mail-header-footer-list', {
        sw_mail_header_footer_list_grid_empty_state_icon: { extend: 'sw_mail_header_footer_list_grid_empty_state' },
    }),
    deprecated('sw-mail-template-index', {
        sw_mail_template_list_content_card: {
            hint: 'Override the sw-mail-template-list or sw-mail-header-footer-list component instead.',
        },
    }),
    deprecated('sw-mail-template-list', {
        sw_mail_template_list_grid_empty_state_icon: { extend: 'sw_mail_template_list_grid_empty_state' },
    }),
    deprecated('sw-manufacturer-detail', {
        sw_manufacturer_detail_sidebar: null,
    }),
    removedComponent('sw-newsletter-recipient-filter-switch', [
        'sw_newsletter_recipient_filter_switch',
        'sw_newsletter_recipient_filter_switch_field',
    ]),
    deprecated('sw-newsletter-recipient-list', {
        sw_newsletter_recipient_list_sidebar_filter_status_not_set: 'sw_newsletter_recipient_list_sidebar_filter_status',
        sw_newsletter_recipient_list_sidebar_filter_status_direct: 'sw_newsletter_recipient_list_sidebar_filter_status',
        sw_newsletter_recipient_list_sidebar_filter_status_opt_in: 'sw_newsletter_recipient_list_sidebar_filter_status',
        sw_newsletter_recipient_list_sidebar_filter_status_opt_out: 'sw_newsletter_recipient_list_sidebar_filter_status',
    }),
    deprecated('sw-order-create-address-modal', {
        sw_order_create_address_modal_empty_state_content: { extend: 'sw_order_create_address_modal_empty_state' },
    }),
    deprecated('sw-order-customer-grid', {
        sw_order_customer_grid_empty_state_icon: { extend: 'sw_order_customer_grid_empty_state' },
    }),
    deprecated('sw-order-detail-details', {
        sw_order_detail_details_shipping_costs: {
            use: 'sw_order_detail_general_line_items_summary_shipping_cost',
            in: 'sw-order-detail-general',
        },
        sw_order_detail_details_order_promotion: null,
    }),
    deprecated('sw-order-line-items-grid-sales-channel', {
        sw_order_line_items_grid_sales_channel_line_item_filter: { extend: 'sw_order_line_items_grid_sales_channel_header' },
    }),
    removedComponent('sw-order-user-card', [
        'sw_order_detail_base_order_overview',
        'sw_order_detail_base_address_modal',
        'sw_order_user_card_row_primary',
        'sw_order_user_card_metadata_container',
        'sw_order_user_card_avatar',
        'sw_order_user_card_metadata',
        'sw_order_user_card_metadata_user_name',
        'sw_order_user_card_metadata_tags',
        'sw_order_detail_base_info_summary',
        'sw_order_detail_base_info_summary_entries',
        'sw_order_user_card_slot_additional_actions',
        'sw_order_detail_base_order_overview_columns',
        'sw_order_detail_base_order_overview_left_column',
        'sw_order_detail_base_order_overview_email',
        'sw_order_detail_base_order_overview_billing_address',
        'sw_order_detail_base_order_overview_left_column_slot',
        'sw_order_detail_base_order_overview_right_column',
        'sw_order_detail_base_order_overview_phone_number',
        'sw_order_detail_base_order_overview_shipping_address',
        'sw_order_detail_base_order_overview_right_column_slot',
        'sw_order_user_card_row_secondary',
        'sw_order_detail_base_secondary_info_order_overview',
        'sw_order_detail_base_secondary_info_order_overview_contents',
        'sw_order_detail_base_secondary_info_order_overview_left_column',
        'sw_order_detail_base_secondary_info_sales_channel',
        'sw_order_detail_base_secondary_info_payment',
        'sw_order_detail_base_secondary_info_delivery',
        'sw_order_detail_base_secondary_info_affiliate',
        'sw_order_detail_base_secondary_info_order_overview_left_column_slot',
        'sw_order_detail_base_secondary_info_order_overview_right_column',
        'sw_order_detail_base_secondary_info_order_language',
        'sw_order_detail_base_secondary_info_last_change',
        'sw_order_detail_base_secondary_info_tracking_codes',
        'sw_order_detail_base_secondary_info_tracking_codes_input',
        'sw_order_detail_base_secondary_info_tracking_codes_input_label',
        'sw_order_detail_base_secondary_info_tracking_codes_input_content',
        'sw_order_detail_base_secondary_info_tracking_codes_display',
        'sw_order_detail_base_secondary_info_tracking_codes_display_label',
        'sw_order_detail_base_secondary_info_tracking_codes_display_content',
        'sw_order_detail_base_secondary_info_tracking_codes_empty',
        'sw_order_detail_base_secondary_info_tracking_codes_empty_label',
        'sw_order_detail_base_secondary_info_tracking_codes_empty_content',
        'sw_order_detail_base_secondary_info_campaign',
        'sw_order_detail_base_secondary_info_order_overview_right_column_slot',
    ]),
    deprecated('sw-product-detail', {
        sw_product_settings_mode: null,
    }),
    deprecated('sw-product-detail-context-prices', {
        sw_product_detail_prices_empty_state_image: { extend: 'sw_product_detail_prices_price_empty_state' },
        sw_product_detail_prices_price_empty_state_text: { extend: 'sw_product_detail_prices_price_empty_state' },
        sw_product_detail_prices_price_empty_state_text_child: { extend: 'sw_product_detail_prices_price_empty_state' },
        sw_product_detail_prices_price_empty_state_text_inherited: { extend: 'sw_product_detail_prices_price_empty_state' },
        sw_product_detail_prices_price_empty_state_text_link: { extend: 'sw_product_detail_prices_price_empty_state' },
        sw_product_detail_prices_price_empty_state_text_not_inherited: {
            extend: 'sw_product_detail_prices_price_empty_state',
        },
        sw_product_detail_prices_price_empty_state_text_empty: { extend: 'sw_product_detail_prices_price_empty_state' },
    }),
    deprecated('sw-product-detail-cross-selling', {
        sw_product_detail_cross_selling_empty_state_actions: { extend: 'sw_product_detail_cross_selling_empty_state' },
        sw_product_detail_cross_selling_empty_state_icon: { extend: 'sw_product_detail_cross_selling_empty_state' },
        sw_product_detail_cross_selling_empty_state_content: { extend: 'sw_product_detail_cross_selling_empty_state' },
        sw_product_detail_cross_selling_empty_state_content_child: { extend: 'sw_product_detail_cross_selling_empty_state' },
        sw_product_detail_cross_selling_empty_state_content_child_inherited: {
            extend: 'sw_product_detail_cross_selling_empty_state',
        },
        sw_product_detail_cross_selling_empty_state_content_child_inherited_link: {
            extend: 'sw_product_detail_cross_selling_empty_state',
        },
        sw_product_detail_cross_selling_empty_state_content_child_not_inherited: {
            extend: 'sw_product_detail_cross_selling_empty_state',
        },
        sw_product_detail_cross_selling_empty_state_content_empty: { extend: 'sw_product_detail_cross_selling_empty_state' },
    }),
    deprecated('sw-product-packaging-form', {
        sw_product_packaging_form_width_field: {
            use: 'sw_product_measurement_form_width_field',
            in: 'sw-product-measurement-form',
        },
        sw_product_packaging_form_height_field: {
            use: 'sw_product_measurement_form_height_field',
            in: 'sw-product-measurement-form',
        },
        sw_product_settings_form_length_field: {
            use: 'sw_product_measurement_form_length_field',
            in: 'sw-product-measurement-form',
        },
        sw_product_settings_form_weight_field: {
            use: 'sw_product_measurement_form_weight_field',
            in: 'sw-product-measurement-form',
        },
    }),
    deprecated('sw-product-properties', {
        sw_product_properties_filled_state_body_empty_state_image: {
            extend: 'sw_product_properties_filled_state_body_empty_state',
        },
        sw_product_properties_empty_state_image: { extend: 'sw_product_properties_empty_state' },
    }),
    removedComponent('sw-product-settings-mode', [
        'sw_product_settings_mode',
        'sw_product_settings_mode_trigger',
        'sw_product_settings_mode_trigger_icon',
        'sw_product_settings_mode_content',
        'sw_product_settings_mode_main',
        'sw_product_settings_mode_advanced_switch',
        'sw_product_settings_mode_devider',
        'sw_product_settings_mode_list',
        'sw_product_settings_mode_item',
        'sw_product_settings_mode_item_visibility_checkbox',
        'sw_product_settings_mode_loader',
    ]),
    removedComponent('sw-promotion-v2-empty-state-hero', [
        'sw_promotion_v2_empty_state_hero',
        'sw_promotion_v2_empty_state_hero_content',
        'sw_promotion_v2_empty_state_hero_content_title',
        'sw_promotion_v2_empty_state_hero_content_description',
        'sw_promotion_v2_empty_state_hero_content_actions',
        'sw_promotion_v2_empty_state_hero_slot_actions',
        'sw_promotion_v2_empty_state_hero_image',
        'sw_promotion_v2_empty_state_hero_image_asset',
    ]),
    deprecated('sw-promotion-v2-individual-codes-behavior', {
        sw_promotion_v2_individual_codes_behavior_empty_state_icon: {
            extend: 'sw_promotion_v2_individual_codes_behavior_empty_state',
        },
    }),
    deprecated('sw-promotion-v2-list', {
        sw_promotion_v2_list_empty_state_actions: { extend: 'sw_promotion_v2_list_empty_state' },
    }),
    deprecated(
        'sw-property-search',
        {
            sw_property_search_empty_image: { extend: 'sw_property_search_empty' },
        },
        ['sw-product-variants-configurator-selection'],
    ),
    deprecated('sw-sales-channel-detail-products', {
        sw_sales_channel_detail_products_card_section_primary_image: {
            extend: 'sw_sales_channel_detail_products_card_section_primary_empty_state',
        },
        sw_sales_channel_detail_products_empty_state_image: { extend: 'sw_sales_channel_detail_products_empty_state' },
    }),
    deprecated('sw-sales-channel-products-assignment-dynamic-product-groups', {
        sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty_icon: {
            extend: 'sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty',
        },
    }),
    deprecated('sw-settings-language-detail', {
        sw_settings_language_detail_content_language_info: null,
    }),
    deprecated('sw-settings-language-list', {
        sw_settings_language_list_smart_bar_header_amount: { extend: 'sw_settings_language_list_smart_bar_header_title' },
        sw_settings_language_list_content_list_column_id: { extend: 'sw_settings_language_list_content_list_columns' },
        sw_settings_language_list_content_list_delete_confirm_action: 'sw_settings_language_list_delete_modal',
        sw_settings_language_list_grid_sidebar_filter: { extend: 'sw_settings_language_list_grid_sidebar' },
        sw_settings_language_list_grid_sidebar_filter_header: { extend: 'sw_settings_language_list_grid_sidebar' },
        sw_settings_language_list_grid_sidebar_filter_header_title: { extend: 'sw_settings_language_list_grid_sidebar' },
        sw_settings_language_list_grid_sidebar_filter_header_icon: { extend: 'sw_settings_language_list_grid_sidebar' },
        sw_settings_language_list_grid_sidebar_filter_header_icon_expanded: {
            extend: 'sw_settings_language_list_grid_sidebar',
        },
        sw_settings_language_list_grid_sidebar_filter_header_icon_not_expanded: {
            extend: 'sw_settings_language_list_grid_sidebar',
        },
        sw_settings_language_list_grid_sidebar_filter_content: { extend: 'sw_settings_language_list_grid_sidebar' },
        sw_settings_language_list_grid_sidebar_filter_content_root_languages: {
            extend: 'sw_settings_language_list_grid_sidebar',
        },
        sw_settings_language_list_grid_sidebar_filter_content_inherited_languages: {
            extend: 'sw_settings_language_list_grid_sidebar',
        },
    }),
    deprecated('sw-settings-listing', {
        sw_settings_listing_content_card_view_options_card_empty_state_icon: {
            extend: 'sw_settings_listing_content_card_view_options_card_empty_state',
        },
    }),
    deprecated('sw-settings-listing-option-criteria-grid', {
        sw_settings_listing_option_criteria_card_empty_state_icon: {
            extend: 'sw_settings_listing_option_criteria_card_empty_state',
        },
    }),
    deprecated('sw-settings-mailer', {
        sw_settings_mailer_headline_agent: null,
    }),
    deprecated('sw-settings-product-feature-sets-values-card', {
        sw_product_feature_set_card_empty_state_image: { extend: 'sw_product_feature_set_card_empty_state' },
        sw_product_feature_set_card_empty_state_label: { extend: 'sw_product_feature_set_card_empty_state' },
    }),
    deprecated('sw-settings-search-excluded-search-terms', {
        sw_settings_search_excluded_search_terms_empty_state_image: {
            extend: 'sw_settings_search_excluded_search_terms_empty_state',
        },
    }),
    deprecated('sw-settings-search-live-search', {
        sw_settings_search_view_live_search_search_icon_wrapper: { extend: 'sw_settings_search_view_live_search_input' },
        sw_settings_search_view_live_search_search_icon: { extend: 'sw_settings_search_view_live_search_input' },
    }),
    deprecated('sw-settings-search-search-index', {
        sw_settings_search_search_index_warning_top: 'sw_settings_search_search_index_warning_bottom',
        sw_settings_search_search_index_rebuild_progress_text: {
            extend: 'sw_settings_search_search_index_rebuild_progress_bar',
        },
    }),
    deprecated('sw-settings-search-searchable-content', {
        sw_settings_search_searchable_show_example: 'sw_settings_search_searchable_show_example_link',
        sw_settings_search_searchable_show_example_link_element: 'sw_settings_search_searchable_show_example_link',
    }),
    deprecated('sw-settings-search-searchable-content-customfields', {
        sw_settings_search_searchable_content_customfields_state_image: {
            extend: 'sw_settings_search_searchable_content_customfields_empty_state',
        },
    }),
    deprecated('sw-settings-search-searchable-content-general', {
        sw_settings_search_searchable_content_general_state_image: {
            extend: 'sw_settings_search_searchable_content_general_empty_state',
        },
    }),
    deprecated('sw-settings-snippet-set-list', {
        sw_settings_snippet_set_list_card_title: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_list_container: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_list_container_header: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_list_container_header_btn_export: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_container_header_btn_edit_set: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_container_header_btn_split: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_container_header_btn_add_set: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_btn_copy_icon: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_list_btn_copy_split: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_btn_copy: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_copy_context_menu: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_copy_context_divider: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_copy_context_menu_items: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_list_grid_template: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_list_grid_column_name: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_list_grid_column_name_link: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_grid_column_name_editor: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_grid_column_changed: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_grid_column_changed_date: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_grid_column_base_file: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_grid_column_base_file_editor: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_grid_column_base_action: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_grid_column_base_action_buttons: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_edit: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_clone: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_card_list_grid_column_base_action_btn_delete: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_columns_delete_modal: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_delete_modal_confirm_delete_text: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_delete_modal_footer: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_delete_modal_cancel: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_delete_modal_confirm: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_columns_clone_modal: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_clone_modal_confirm_delete_text: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
        sw_settings_snippet_set_list_clone_modal_footer: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_clone_modal_cancel: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_clone_modal_confirm: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_list_grid_pagination: { extend: 'sw_settings_snippet_set_list_card_list_grid' },
        sw_settings_snippet_set_list_card_list_grid_pagination_bar: {
            extend: 'sw_settings_snippet_set_list_card_list_grid',
        },
    }),
    deprecated('sw-tax-rule-card', {
        sw_tax_rule_card_empty_state_image: { extend: 'sw_tax_rule_card_empty_state' },
        sw_tax_rule_card_empty_state_label: { extend: 'sw_tax_rule_card_empty_state' },
    }),
    deprecated('sw-users-permissions-user-listing', {
        sw_settings_user_list_delete_modal_input__confirm_password: {
            hint: 'Extend the sw-verify-user-modal component instead.',
        },
    }),
] satisfies RemovedBlocks[];
