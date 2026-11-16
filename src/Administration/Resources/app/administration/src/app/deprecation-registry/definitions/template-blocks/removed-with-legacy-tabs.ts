/**
 * @sw-package framework
 *
 * Twig blocks that 6.8 removes with the legacy sw-tabs branch of `feature.isActive('v6.8.0.0')`, per core component.
 */

import { legacyTabs } from './removed-blocks';
import type { RemovedBlocks } from './removed-blocks';

export default [
    legacyTabs('sw-category-view', {
        sw_category_view_tabs_general: { tab: 'categoryViewTabs' },
        sw_category_view_tabs_products: { tab: 'categoryViewTabs' },
        sw_category_view_tabs_cms: { tab: 'categoryViewTabs' },
        sw_category_view_tabs_seo: { tab: 'categoryViewTabs' },
    }),
    legacyTabs(
        'sw-custom-field-set-renderer',
        {
            sw_custom_field_set_renderer_card_tabs: 'sw_custom_field_set_renderer_card_mt_tabs',
            sw_custom_field_set_renderer_card_tabs_content: 'sw_custom_field_set_renderer_card_mt_tabs_content',
            sw_custom_field_set_renderer_card_form_renderer: 'sw_custom_field_set_renderer_card_mt_tabs_form_renderer',
        },
        ['sw-bulk-edit-custom-fields'],
    ),
    legacyTabs('sw-custom-field-translated-labels', {
        sw_custom_field_translated_labels_translated_tabs: 'sw_custom_field_translated_labels_translated_mt_tabs',
        sw_custom_field_translated_labels_translated_content: 'sw_custom_field_translated_labels_translated_mt_content',
        sw_custom_field_translated_labels_translated_content_field:
            'sw_custom_field_translated_labels_translated_mt_content_field',
    }),
    legacyTabs('sw-customer-detail', {
        sw_customer_detail_content_tab_general: { tab: 'customerDetailTabs' },
        sw_customer_detail_content_tab_addresses: { tab: 'customerDetailTabs' },
        sw_customer_detail_content_tab_order: { tab: 'customerDetailTabs' },
        sw_customer_detail_content_tab_after: { tab: 'customerDetailTabs' },
    }),
    legacyTabs('sw-flow-detail', {
        sw_flow_tabs_header_general: { tab: 'flowDetailTabs' },
        sw_flow_tabs_header_builder: { tab: 'flowDetailTabs' },
        sw_flow_tabs_header_extension: { tab: 'flowDetailTabs' },
    }),
    legacyTabs('sw-flow-index', {
        sw_flow_tabs_header_extension: { tab: 'flowTabs' },
    }),
    legacyTabs('sw-flow-rule-modal', {
        sw_flow_rule_headers: { tab: 'tabs' },
        sw_flow_rule_modal_tab_detail: { tab: 'tabs' },
        sw_flow_rule_modal_tab_rule: { tab: 'tabs' },
        sw_flow_rule_modal_content: 'sw_flow_rule_modal_mt_content',
        sw_flow_rule_modal_tab_detail_content: 'sw_flow_rule_modal_mt_tab_detail_content',
        sw_flow_rule_modal_detail_name: 'sw_flow_rule_modal_mt_detail_name',
        sw_flow_rule_modal_detail_priority: 'sw_flow_rule_modal_mt_detail_priority',
        sw_flow_rule_modal_detail_description: 'sw_flow_rule_modal_mt_detail_description',
        sw_flow_rule_modal_detail_type: 'sw_flow_rule_modal_mt_detail_type',
        sw_flow_rule_modal_tab_rule_content: 'sw_flow_rule_modal_mt_tab_rule_content',
        sw_flow_rule_modal_conditions_card: 'sw_flow_rule_modal_mt_conditions_card',
    }),
    legacyTabs('sw-import-export', {
        sw_import_export_tabs_import: { tab: 'importExportTabs' },
        sw_import_export_tabs_export: { tab: 'importExportTabs' },
        sw_import_export_tabs_profiles: { tab: 'importExportTabs' },
    }),
    legacyTabs('sw-import-export-edit-profile-modal', {
        sw_import_export_edit_profile_modal_tabs_general: { tab: 'profileTabs' },
        sw_import_export_edit_profile_modal_tabs_field_mappings: { tab: 'profileTabs' },
        sw_import_export_edit_profile_modal_tabs_field_advanced: { tab: 'profileTabs' },
        sw_import_export_edit_profile_modal_tabs_general_import_settings:
            'sw_import_export_edit_profile_modal_mt_tabs_general_import_settings',
        sw_import_export_edit_profile_modal_tabs_mappings: 'sw_import_export_edit_profile_modal_mt_tabs_mappings',
        sw_import_export_edit_profile_modal_tabs_mappings_text: 'sw_import_export_edit_profile_modal_mt_tabs_mappings_text',
        sw_import_export_edit_profile_modal_tabs_mappings_mapping:
            'sw_import_export_edit_profile_modal_mt_tabs_mappings_mapping',
        sw_import_export_edit_profile_modal_tabs_advanced: 'sw_import_export_edit_profile_modal_mt_tabs_advanced',
        sw_import_export_edit_profile_modal_tabs_advanced_text: 'sw_import_export_edit_profile_modal_mt_tabs_advanced_text',
        sw_import_export_edit_profile_modal_tabs_advanced_identifiers:
            'sw_import_export_edit_profile_modal_mt_tabs_advanced_identifiers',
    }),
    legacyTabs('sw-landing-page-view', {
        sw_landing_page_view_tabs_general: { tab: 'landingPageViewTabs' },
        sw_landing_page_view_tabs_cms: { tab: 'landingPageViewTabs' },
    }),
    legacyTabs('sw-media-modal-folder-settings', {
        sw_media_modal_folder_settings_tab_item_settings: { tab: 'tabs' },
        sw_media_modal_folder_settings_tab_item_thumbnails: { tab: 'tabs' },
        sw_media_modal_folder_settings_tab_content_settings: 'sw_media_modal_folder_settings_mt_tab_content_settings',
        sw_media_modal_folder_settings_name_field: 'sw_media_modal_folder_settings_mt_name_field',
        sw_media_modal_folder_settings_default_folder: 'sw_media_modal_folder_settings_mt_default_folder',
        sw_media_modal_folder_settings_tab_content_thumbnails: 'sw_media_modal_folder_settings_mt_tab_content_thumbnails',
        sw_media_modal_folder_settings_tab_content_thumbnails_left_container:
            'sw_media_modal_folder_settings_mt_tab_content_thumbnails_left_container',
        sw_media_modal_folder_settings_inherit_settings_field: 'sw_media_modal_folder_settings_mt_inherit_settings_field',
        sw_media_modal_folder_settings_generate_thumbnails_field:
            'sw_media_modal_folder_settings_mt_generate_thumbnails_field',
        sw_media_modal_folder_settings_keep_proportions_field: 'sw_media_modal_folder_settings_mt_keep_proportions_field',
        sw_media_modal_folder_settings_thumbnails_quality_field:
            'sw_media_modal_folder_settings_mt_thumbnails_quality_field',
        sw_media_modal_folder_settings_tab_content_thumbnails_right_container:
            'sw_media_modal_folder_settings_mt_tab_content_thumbnails_right_container',
        sw_media_modal_folder_settings_thumbnail_list_caption: 'sw_media_modal_folder_settings_mt_thumbnail_list_caption',
        sw_media_modal_folder_settings_thumbnail_list_container:
            'sw_media_modal_folder_settings_mt_thumbnail_list_container',
        sw_media_modal_folder_settings_thumbnail_list: 'sw_media_modal_folder_settings_mt_thumbnail_list',
        sw_media_modal_folder_settings_thumbnail_size: 'sw_media_modal_folder_settings_mt_thumbnail_size',
        sw_media_modal_folder_settings_thumbnail_size_switch: 'sw_media_modal_folder_settings_mt_thumbnail_size_switch',
        sw_media_modal_folder_settings_thumbnail_size_delete_button:
            'sw_media_modal_folder_settings_mt_thumbnail_size_delete_button',
    }),
    legacyTabs('sw-media-modal-v2', {
        sw_media_modal_v2_tab_items: { tab: 'mediaModalTabs' },
        sw_media_modal_v2_tab_item_library: { tab: 'mediaModalTabs' },
        sw_media_modal_v2_tab_item_upload: { tab: 'mediaModalTabs' },
        sw_media_modal_v2_tab_content: 'sw_media_modal_v2_mt_tab_content',
        sw_media_modal_v2_tab_content_library: 'sw_media_modal_v2_mt_tab_content_library',
        sw_media_modal_v2_navigation_and_search: 'sw_media_modal_v2_mt_navigation_and_search',
        sw_media_modal_v2_folder_breadcrumbs: 'sw_media_modal_v2_mt_folder_breadcrumbs',
        sw_media_modal_v2_search_field: 'sw_media_modal_v2_mt_search_field',
        sw_media_modal_v2_media_library: 'sw_media_modal_v2_mt_media_library',
        sw_media_modal_v2_tab_content_upload: 'sw_media_modal_v2_mt_tab_content_upload',
        sw_media_modal_v2_upload_component: 'sw_media_modal_v2_mt_upload_component',
        sw_media_modal_v2_uploaded_items: 'sw_media_modal_v2_mt_uploaded_items',
    }),
    legacyTabs('sw-order-address-modal', {
        sw_order_address_modal_tabs: { tab: 'addressModalTabs' },
        sw_order_address_modal_tab_edit_address: { tab: 'addressModalTabs' },
        sw_order_address_modal_tab_select_address: { tab: 'addressModalTabs' },
        sw_order_address_modal_tabs_content: 'sw_order_address_modal_mt_tabs_content',
        sw_order_address_modal_tabs_content_edit_address: 'sw_order_address_modal_mt_tabs_content_edit_address',
        sw_order_address_modal_tabs_content_select_address: 'sw_order_address_modal_mt_tabs_content_select_address',
    }),
    legacyTabs('sw-order-create', {
        sw_order_create_content_tabs_general: { tab: 'orderCreateTabs' },
        sw_order_create_content_tabs_details: { tab: 'orderCreateTabs' },
    }),
    legacyTabs('sw-order-create-initial-modal', {
        sw_order_create_modal_tabs_customer: { tab: 'orderCreateInitialModalTabs' },
        sw_order_create_modal_tabs_products: { tab: 'orderCreateInitialModalTabs' },
        sw_order_create_modal_tabs_options: { tab: 'orderCreateInitialModalTabs' },
        sw_order_create_modal_tabs_extension: { tab: 'orderCreateInitialModalTabs' },
        sw_order_create_modal_tabs_content: 'sw_order_create_modal_mt_tabs_content',
        sw_order_create_modal_tabs_content_customer: 'sw_order_create_modal_mt_tabs_content_customer',
        sw_order_create_modal_tabs_content_products: 'sw_order_create_modal_mt_tabs_content_products',
        sw_order_create_modal_tabs_content_options: 'sw_order_create_modal_mt_tabs_content_options',
    }),
    legacyTabs('sw-order-detail', {
        sw_order_detail_content_tabs_general: { tab: 'orderDetailTabs' },
        sw_order_detail_content_tabs_details: { tab: 'orderDetailTabs' },
        sw_order_detail_content_tabs_documents: { tab: 'orderDetailTabs' },
        sw_order_detail_content_tabs_extension: { tab: 'orderDetailTabs' },
    }),
    legacyTabs('sw-order-new-customer-modal', {
        sw_order_new_customer_modal_tabs_details: { tab: 'newCustomerModalTabs' },
        sw_order_new_customer_modal_tabs_billing: { tab: 'newCustomerModalTabs' },
        sw_order_new_customer_modal_tabs_shipping: { tab: 'newCustomerModalTabs' },
        sw_order_new_customer_modal_content_details: 'sw_order_new_customer_modal_mt_tabs_content_details',
        sw_order_new_customer_modal_content_details_guest: 'sw_order_new_customer_modal_mt_tabs_content_details_guest',
        sw_order_new_customer_modal_content_details_form: 'sw_order_new_customer_modal_mt_tabs_content_details_form',
        sw_order_new_customer_modal_content_shipping: 'sw_order_new_customer_modal_mt_tabs_content_shipping',
        sw_order_new_customer_modal_content_shipping_same_billing:
            'sw_order_new_customer_modal_mt_tabs_content_shipping_same_billing',
        sw_order_new_customer_modal_content_shipping_form: 'sw_order_new_customer_modal_mt_tabs_content_shipping_form',
        sw_order_new_customer_modal_content_billing: 'sw_order_new_customer_modal_mt_tabs_content_billing',
        sw_order_new_customer_modal_content_billing_form: 'sw_order_new_customer_modal_mt_tabs_content_billing_form',
    }),
    legacyTabs('sw-product-detail', {
        sw_product_detail_content_tabs_general: { tab: 'productDetailTabs' },
        sw_product_detail_content_tabs_specifications: { tab: 'productDetailTabs' },
        sw_product_detail_content_tabs_advanced_prices: { tab: 'productDetailTabs' },
        sw_product_detail_content_tabs_advanced_variants: { tab: 'productDetailTabs' },
        sw_product_detail_content_tabs_layout: { tab: 'productDetailTabs' },
        sw_product_detail_content_tabs_seo: { tab: 'productDetailTabs' },
        sw_product_detail_content_tabs_cross_selling: { tab: 'productDetailTabs' },
        sw_product_detail_content_tabs_reviews: { tab: 'productDetailTabs' },
        sw_product_detail_content_tabs_additional: { tab: 'productDetailTabs' },
    }),
    legacyTabs('sw-product-modal-delivery', {
        sw_product_modal_delivery_sidebar_tabs_items: { tab: 'deliveryTabs' },
    }),
    legacyTabs('sw-product-modal-variant-generation', {
        sw_product_modal_variant_generation_sidebar_tabs_items: { tab: 'variantGenerationTabs' },
        sw_product_modal_variant_generation_sidebar_tabs_item_options: { tab: 'variantGenerationTabs' },
        sw_product_modal_variant_generation_sidebar_tabs_item_prices: { tab: 'variantGenerationTabs' },
        sw_product_modal_variant_generation_sidebar_tabs_item_restrictions: { tab: 'variantGenerationTabs' },
    }),
    legacyTabs('sw-profile-index', {
        sw_profile_index_tabs_item_general: { tab: 'profileTabs' },
        sw_profile_index_tabs_item_search_preferences: { tab: 'profileTabs' },
    }),
    legacyTabs('sw-promotion-v2-detail', {
        sw_promotion_v2_detail_content_tabs_general: { tab: 'promotionDetailTabs' },
        sw_promotion_v2_detail_content_tabs_conditions: { tab: 'promotionDetailTabs' },
        sw_promotion_v2_detail_content_tabs_discounts: { tab: 'promotionDetailTabs' },
    }),
    legacyTabs(
        'sw-sales-channel-detail',
        {
            sw_sales_channel_detail_content_tab_general: { tab: 'salesChannelDetailTabs' },
            sw_sales_channel_detail_content_tab_product_export_insights: { tab: 'salesChannelDetailTabs' },
            sw_sales_channel_detail_content_tab_products: { tab: 'salesChannelDetailTabs' },
            sw_sales_channel_detail_content_tab_theme: { tab: 'salesChannelDetailTabs' },
            sw_sales_channel_detail_content_tab_agentic_commerce_integration: { tab: 'salesChannelDetailTabs' },
            sw_sales_channel_detail_content_tab_export_template: { tab: 'salesChannelDetailTabs' },
            sw_sales_channel_detail_content_tab_analytics: { tab: 'salesChannelDetailTabs' },
        },
        ['sw-sales-channel-create'],
    ),
    legacyTabs('sw-sales-channel-products-assignment-modal', {
        sw_sales_channel_products_assignment_modal_tabs_single_products: { tab: 'productAssignmentTabs' },
        sw_sales_channel_products_assignment_modal_tabs_categories: { tab: 'productAssignmentTabs' },
        sw_sales_channel_products_assignment_modal_tab_dynamic_product_groups: { tab: 'productAssignmentTabs' },
        sw_sales_channel_products_assignment_modal_tab_content_single_products:
            'sw_sales_channel_products_assignment_modal_mt_tab_content_single_products',
        sw_sales_channel_products_assignment_modal_tab_content_categories:
            'sw_sales_channel_products_assignment_modal_mt_tab_content_categories',
        sw_sales_channel_products_assignment_modal_tab_content_dynamic_product_groups:
            'sw_sales_channel_products_assignment_modal_mt_tab_content_dynamic_product_groups',
    }),
    legacyTabs(
        'sw-settings-country-detail',
        {
            sw_setting_country_tabs_setting: { tab: 'countryTabs' },
            sw_setting_country_tabs_state: { tab: 'countryTabs' },
            sw_setting_country_tabs_address_handling: { tab: 'countryTabs' },
            sw_setting_country_tabs_extension: { tab: 'countryTabs' },
        },
        ['sw-settings-country-create'],
    ),
    legacyTabs(
        'sw-settings-logging-entry-info',
        {
            sw_settings_logging_entry_info_tab_items: { tab: 'entryInfoTabs' },
            sw_settings_logging_entry_info_content: 'sw_settings_logging_entry_info_mt_content',
            sw_settings_logging_entry_info_raw_content: 'sw_settings_logging_entry_info_mt_raw_content',
        },
        ['sw-settings-logging-mail-sent-info'],
    ),
    legacyTabs('sw-settings-rule-detail', {
        sw_settings_rule_detail_tab_items: { tab: 'ruleDetailTabs' },
    }),
    legacyTabs('sw-settings-search', {
        sw_setting_search_tabs_general: { tab: 'settingsSearchTabs' },
        sw_setting_search_tabs_live_search: { tab: 'settingsSearchTabs' },
        sw_setting_search_tabs_after: { tab: 'settingsSearchTabs' },
    }),
    legacyTabs('sw-settings-search-searchable-content', {
        sw_settings_search_searchable_content_general_tab_title: { tab: 'searchableContentTabs' },
        sw_settings_search_searchable_content_general_tab_item: 'sw_settings_search_searchable_content_mt_general_tab_item',
    }),
    legacyTabs('sw-settings-tag-detail-modal', {
        sw_settings_tag_detail_modal_tabs_general: { tab: 'tagDetailModalTabs' },
        sw_settings_tag_detail_modal_tabs_assignments: { tab: 'tagDetailModalTabs' },
        sw_settings_tag_detail_modal_tabs_general_tab: 'sw_settings_tag_detail_modal_mt_tabs_general_tab',
        sw_settings_tag_detail_modal_tabs_assignments_tab: 'sw_settings_tag_detail_modal_mt_tabs_assignments_tab',
    }),
    legacyTabs('sw-theme-manager-detail', {
        sw_theme_manager_detail_content_inheritance: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_content_inheritance_icon: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_content_inheritance_text: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_content_info: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_content_info_image: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_content_info_content: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_content_info_context_button: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_context_button_option_rename: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_context_button_option_create: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_context_button_option_reset: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_context_button_option_delete: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_content_areas: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_content_sections: { extend: 'sw_theme_manager_detail_content' },
        sw_theme_manager_detail_content_fields: { extend: 'sw_theme_manager_detail_content' },
    }),
] satisfies RemovedBlocks[];
