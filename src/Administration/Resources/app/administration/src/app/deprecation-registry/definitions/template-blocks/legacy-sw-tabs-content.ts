/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { renameBlocks } from './block-steps';

/** Old block → its counterpart in the `mt-tabs` branch, with `mt` in its name and the same content, per component. */
const COUNTERPARTS: Record<string, Record<string, string>> = {
    'sw-cms-el-config-buy-box': {
        sw_cms_element_buy_box_config_content_warning: 'sw_cms_element_buy_box_config_mt_content_warning',
        sw_cms_element_buy_box_config_options: 'sw_cms_element_buy_box_config_mt_options',
        sw_cms_element_buy_box_config_product_select: 'sw_cms_element_buy_box_config_mt_product_select',
        sw_cms_element_buy_box_config_product_select_result_item:
            'sw_cms_element_buy_box_config_mt_product_select_result_item',
    },
    'sw-cms-el-config-cross-selling': {
        sw_cms_element_cross_selling_config_content: 'sw_cms_element_cross_selling_config_mt_content',
        sw_cms_element_cross_selling_config_content_products: 'sw_cms_element_cross_selling_config_mt_content_products',
        sw_cms_element_cross_selling_config_content_warning_text:
            'sw_cms_element_cross_selling_config_mt_content_warning_text',
        sw_cms_element_cross_selling_config_options: 'sw_cms_element_cross_selling_config_mt_options',
        sw_cms_element_cross_selling_config_options_box_layout: 'sw_cms_element_cross_selling_config_mt_options_box_layout',
        sw_cms_element_cross_selling_config_options_display_mode:
            'sw_cms_element_cross_selling_config_mt_options_display_mode',
        sw_cms_element_cross_selling_config_options_min_width: 'sw_cms_element_cross_selling_config_mt_options_min_width',
        sw_cms_element_cross_selling_config_options_speed: 'sw_cms_element_cross_selling_config_mt_options_speed',
    },
    'sw-cms-el-config-form': {
        sw_cms_el_form_config_content: 'sw_cms_el_form_config_mt_content',
        sw_cms_el_form_config_content_form_confirmation_text: 'sw_cms_el_form_config_mt_content_form_confirmation_text',
        sw_cms_el_form_config_content_form_title: 'sw_cms_el_form_config_mt_content_form_title',
        sw_cms_el_form_config_content_form_type: 'sw_cms_el_form_config_mt_content_form_type',
        sw_cms_el_form_config_options: 'sw_cms_el_form_config_mt_options',
    },
    'sw-cms-el-config-image-gallery': {
        sw_cms_element_image_gallery_config_content: 'sw_cms_element_image_gallery_config_mt_content',
        sw_cms_element_image_gallery_config_media_list_selection:
            'sw_cms_element_image_gallery_config_mt_media_list_selection',
        sw_cms_element_image_gallery_config_media_mapping_preview:
            'sw_cms_element_image_gallery_config_mt_media_mapping_preview',
        sw_cms_element_image_gallery_config_media_modal: 'sw_cms_element_image_gallery_config_mt_media_modal',
        sw_cms_element_image_gallery_config_media_preview_info: 'sw_cms_element_image_gallery_config_mt_media_preview_info',
        sw_cms_element_image_gallery_config_media_preview_item: 'sw_cms_element_image_gallery_config_mt_media_preview_item',
        sw_cms_element_image_gallery_config_media_preview_list: 'sw_cms_element_image_gallery_config_mt_media_preview_list',
        sw_cms_element_image_gallery_config_media_selection: 'sw_cms_element_image_gallery_config_mt_media_selection',
        sw_cms_element_image_gallery_config_media_upload_listener:
            'sw_cms_element_image_gallery_config_mt_media_upload_listener',
        sw_cms_element_image_gallery_config_settings: 'sw_cms_element_image_gallery_config_mt_settings',
        sw_cms_element_image_gallery_config_settings_aspect_ratio_magnifier_over_gallery_toggles:
            'sw_cms_element_image_gallery_config_mt_settings_aspect_ratio_magnifier_over_gallery_toggles',
        sw_cms_element_image_gallery_config_settings_display_mode:
            'sw_cms_element_image_gallery_config_mt_settings_display_mode',
        sw_cms_element_image_gallery_config_settings_display_mode_select:
            'sw_cms_element_image_gallery_config_mt_settings_display_mode_select',
        sw_cms_element_image_gallery_config_settings_min_height:
            'sw_cms_element_image_gallery_config_mt_settings_min_height',
        sw_cms_element_image_gallery_config_settings_navigation:
            'sw_cms_element_image_gallery_config_mt_settings_navigation',
        sw_cms_element_image_gallery_config_settings_navigation_arrow_position:
            'sw_cms_element_image_gallery_config_mt_settings_navigation_arrow_position',
        sw_cms_element_image_gallery_config_settings_navigation_dots_position:
            'sw_cms_element_image_gallery_config_mt_settings_navigation_dots_position',
        sw_cms_element_image_gallery_config_settings_navigation_preview_position:
            'sw_cms_element_image_gallery_config_mt_settings_navigation_preview_position',
        sw_cms_element_image_gallery_config_settings_toggle_fullscreen:
            'sw_cms_element_image_gallery_config_mt_settings_toggle_fullscreen',
        sw_cms_element_image_gallery_config_settings_toggle_keep_aspect_ratio_on_zoom:
            'sw_cms_element_image_gallery_config_mt_settings_toggle_keep_aspect_ratio_on_zoom',
        sw_cms_element_image_gallery_config_settings_toggle_magnifier_over_gallery:
            'sw_cms_element_image_gallery_config_mt_settings_toggle_magnifier_over_gallery',
        sw_cms_element_image_gallery_config_settings_toggle_zoom:
            'sw_cms_element_image_gallery_config_mt_settings_toggle_zoom',
        sw_cms_element_image_gallery_config_settings_use_fetch_priority_on_first_item:
            'sw_cms_element_image_gallery_config_mt_settings_use_fetch_priority_on_first_item',
        sw_cms_element_image_gallery_config_settings_vertical_align:
            'sw_cms_element_image_gallery_config_mt_settings_vertical_align',
        sw_cms_element_image_gallery_config_settings_zoom_toggles:
            'sw_cms_element_image_gallery_config_mt_settings_zoom_toggles',
    },
    'sw-cms-el-config-image-slider': {
        sw_cms_element_image_gallery_config_settings_display_mode:
            'sw_cms_element_image_slider_config_mt_settings_display_mode',
        sw_cms_element_image_gallery_config_settings_display_mode_select:
            'sw_cms_element_image_slider_config_mt_settings_display_mode_select',
        sw_cms_element_image_gallery_config_settings_min_height: 'sw_cms_element_image_slider_config_mt_settings_min_height',
        sw_cms_element_image_gallery_config_settings_vertical_align:
            'sw_cms_element_image_slider_config_mt_settings_vertical_align',
        sw_cms_element_image_slider_config_content: 'sw_cms_element_image_slider_config_mt_content',
        sw_cms_element_image_slider_config_media_modal: 'sw_cms_element_image_slider_config_mt_media_modal',
        sw_cms_element_image_slider_config_media_selection: 'sw_cms_element_image_slider_config_mt_media_selection',
        sw_cms_element_image_slider_config_media_upload_listener:
            'sw_cms_element_image_slider_config_mt_media_upload_listener',
        sw_cms_element_image_slider_config_settings: 'sw_cms_element_image_slider_config_mt_settings',
        sw_cms_element_image_slider_config_settings_auto_slide: 'sw_cms_element_image_slider_config_mt_settings_auto_slide',
        sw_cms_element_image_slider_config_settings_autoplay_timeout:
            'sw_cms_element_image_slider_config_mt_settings_autoplay_timeout',
        sw_cms_element_image_slider_config_settings_display_mode:
            'sw_cms_element_image_slider_config_mt_settings_display_mode',
        sw_cms_element_image_slider_config_settings_display_mode_select:
            'sw_cms_element_image_slider_config_mt_settings_display_mode_select',
        sw_cms_element_image_slider_config_settings_link_aria_label:
            'sw_cms_element_image_slider_config_mt_settings_link_aria_label',
        sw_cms_element_image_slider_config_settings_link_target:
            'sw_cms_element_image_slider_config_mt_settings_link_target',
        sw_cms_element_image_slider_config_settings_link_url: 'sw_cms_element_image_slider_config_mt_settings_link_url',
        sw_cms_element_image_slider_config_settings_links: 'sw_cms_element_image_slider_config_mt_settings_links',
        sw_cms_element_image_slider_config_settings_min_height: 'sw_cms_element_image_slider_config_mt_settings_min_height',
        sw_cms_element_image_slider_config_settings_navigation: 'sw_cms_element_image_slider_config_mt_settings_navigation',
        sw_cms_element_image_slider_config_settings_navigation_arrow_position:
            'sw_cms_element_image_slider_config_mt_settings_navigation_arrow_position',
        sw_cms_element_image_slider_config_settings_navigation_dots_position:
            'sw_cms_element_image_slider_config_mt_settings_navigation_dots_position',
        sw_cms_element_image_slider_config_settings_speed: 'sw_cms_element_image_slider_config_mt_settings_speed',
        sw_cms_element_image_slider_config_settings_use_fetch_priority_on_first_item:
            'sw_cms_element_image_slider_config_mt_settings_use_fetch_priority_on_first_item',
        sw_cms_element_image_slider_config_settings_vertical_align:
            'sw_cms_element_image_slider_config_mt_settings_vertical_align',
    },
    'sw-cms-el-config-product-description-reviews': {
        sw_cms_el_product_description_rating_config_options: 'sw_cms_el_product_description_rating_config_mt_options',
        sw_cms_el_product_description_rating_config_options_alignment:
            'sw_cms_el_product_description_rating_config_mt_options_alignment',
        sw_cms_element_product_description_reviews_config_content:
            'sw_cms_element_product_description_reviews_config_mt_content',
        sw_cms_element_product_description_reviews_config_product_select:
            'sw_cms_element_product_description_reviews_config_mt_product_select',
        sw_cms_element_product_description_reviews_warning: 'sw_cms_element_product_description_reviews_mt_warning',
    },
    'sw-cms-el-config-product-listing': {
        sw_cms_element_product_listing_config_available_sortings:
            'sw_cms_element_product_listing_config_mt_available_sortings',
        sw_cms_element_product_listing_config_default_sorting: 'sw_cms_element_product_listing_config_mt_default_sorting',
        sw_cms_element_product_listing_config_filter_by_manufacturer:
            'sw_cms_element_product_listing_config_mt_filter_by_manufacturer',
        sw_cms_element_product_listing_config_filter_by_price: 'sw_cms_element_product_listing_config_mt_filter_by_price',
        sw_cms_element_product_listing_config_filter_by_rating: 'sw_cms_element_product_listing_config_mt_filter_by_rating',
        sw_cms_element_product_listing_config_filter_by_wrapper:
            'sw_cms_element_product_listing_config_mt_filter_by_wrapper',
        sw_cms_element_product_listing_config_filter_empty_state:
            'sw_cms_element_product_listing_config_mt_filter_empty_state',
        sw_cms_element_product_listing_config_filter_for_free_shipping:
            'sw_cms_element_product_listing_config_mt_filter_for_free_shipping',
        sw_cms_element_product_listing_config_filter_info: 'sw_cms_element_product_listing_config_mt_filter_info',
        sw_cms_element_product_listing_config_filter_properties_as_filter:
            'sw_cms_element_product_listing_config_mt_filter_properties_as_filter',
        sw_cms_element_product_listing_config_filter_properties_as_filter_info_text:
            'sw_cms_element_product_listing_config_mt_filter_properties_as_filter_info_text',
        sw_cms_element_product_listing_config_filter_properties_as_filter_switch:
            'sw_cms_element_product_listing_config_mt_filter_properties_as_filter_switch',
        sw_cms_element_product_listing_config_filter_properties_wrapper:
            'sw_cms_element_product_listing_config_mt_filter_properties_wrapper',
        sw_cms_element_product_listing_config_filter_property_grid:
            'sw_cms_element_product_listing_config_mt_filter_property_grid',
        sw_cms_element_product_listing_config_filter_property_grid_column_status:
            'sw_cms_element_product_listing_config_mt_filter_property_grid_column_status',
        sw_cms_element_product_listing_config_filter_property_grid_columns:
            'sw_cms_element_product_listing_config_mt_filter_property_grid_columns',
        sw_cms_element_product_listing_config_filter_property_grid_pagination:
            'sw_cms_element_product_listing_config_mt_filter_property_grid_pagination',
        sw_cms_element_product_listing_config_filter_property_search:
            'sw_cms_element_product_listing_config_mt_filter_property_search',
        sw_cms_element_product_listing_config_filter_spacer: 'sw_cms_element_product_listing_config_mt_filter_spacer',
        sw_cms_element_product_listing_config_info: 'sw_cms_element_product_listing_config_mt_info',
        sw_cms_element_product_listing_config_layout_select: 'sw_cms_element_product_listing_config_mt_layout_select',
        sw_cms_element_product_listing_config_show_sorting: 'sw_cms_element_product_listing_config_mt_show_sorting',
        sw_cms_element_product_listing_config_sorting_grid: 'sw_cms_element_product_listing_config_mt_sorting_grid',
        sw_cms_element_product_listing_config_use_default_sorting:
            'sw_cms_element_product_listing_config_mt_use_default_sorting',
        sw_entity_multi_select_base_results_list_result_label:
            'sw_cms_element_product_listing_config_mt_available_sortings_result_label',
    },
    'sw-cms-el-config-product-slider': {
        sw_cms_element_product_slider_config_content: 'sw_cms_element_product_slider_config_mt_content',
        sw_cms_element_product_slider_config_content_product_assignment_type:
            'sw_cms_element_product_slider_config_mt_content_product_assignment_type',
        sw_cms_element_product_slider_config_content_product_stream_limit:
            'sw_cms_element_product_slider_config_mt_content_product_stream_limit',
        sw_cms_element_product_slider_config_content_product_stream_performance_hint:
            'sw_cms_element_product_slider_config_mt_content_product_stream_performance_hint',
        sw_cms_element_product_slider_config_content_product_stream_preview_link:
            'sw_cms_element_product_slider_config_mt_content_product_stream_preview_link',
        sw_cms_element_product_slider_config_content_product_stream_select:
            'sw_cms_element_product_slider_config_mt_content_product_stream_select',
        sw_cms_element_product_slider_config_content_product_stream_sorting:
            'sw_cms_element_product_slider_config_mt_content_product_stream_sorting',
        sw_cms_element_product_slider_config_content_products: 'sw_cms_element_product_slider_config_mt_content_products',
        sw_cms_element_product_slider_config_content_title: 'sw_cms_element_product_slider_config_mt_content_title',
        sw_cms_element_product_slider_config_settings: 'sw_cms_element_product_slider_config_mt_settings',
        sw_cms_element_product_slider_config_settings_autoplay_timeout:
            'sw_cms_element_product_slider_config_mt_settings_autoplay_timeout',
        sw_cms_element_product_slider_config_settings_border: 'sw_cms_element_product_slider_config_mt_settings_border',
        sw_cms_element_product_slider_config_settings_box_layout:
            'sw_cms_element_product_slider_config_mt_settings_box_layout',
        sw_cms_element_product_slider_config_settings_display_mode:
            'sw_cms_element_product_slider_config_mt_settings_display_mode',
        sw_cms_element_product_slider_config_settings_min_width:
            'sw_cms_element_product_slider_config_mt_settings_min_width',
        sw_cms_element_product_slider_config_settings_navigation_arrows:
            'sw_cms_element_product_slider_config_mt_settings_navigation_arrows',
        sw_cms_element_product_slider_config_settings_rotate: 'sw_cms_element_product_slider_config_mt_settings_rotate',
        sw_cms_element_product_slider_config_settings_speed: 'sw_cms_element_product_slider_config_mt_settings_speed',
        sw_cms_element_product_slider_config_settings_vertical_align:
            'sw_cms_element_product_slider_config_mt_settings_vertical_align',
    },
    'sw-cms-el-config-text': {
        sw_cms_el_text_config_content: 'sw_cms_el_text_config_mt_content',
        sw_cms_el_text_config_settings: 'sw_cms_el_text_config_mt_settings',
        sw_cms_el_text_config_settings_vertical_align: 'sw_cms_el_text_config_mt_settings_vertical_align',
    },
    'sw-cms-layout-assignment-modal': {
        sw_cms_layout_assignment_modal_category_select: 'sw_cms_layout_assignment_modal_mt_category_select',
        sw_cms_layout_assignment_modal_category_select_field: 'sw_cms_layout_assignment_modal_mt_category_select_field',
        sw_cms_layout_assignment_modal_landing_page_select: 'sw_cms_layout_assignment_modal_mt_landing_page_select',
        sw_cms_layout_assignment_modal_landing_page_select_field:
            'sw_cms_layout_assignment_modal_mt_landing_page_select_field',
        sw_cms_layout_assignment_modal_product_detail_pages_column_manufacturer:
            'sw_cms_layout_assignment_modal_mt_product_detail_pages_column_manufacturer',
        sw_cms_layout_assignment_modal_product_detail_pages_column_name:
            'sw_cms_layout_assignment_modal_mt_product_detail_pages_column_name',
        sw_cms_layout_assignment_modal_product_detail_pages_empty_state:
            'sw_cms_layout_assignment_modal_mt_product_detail_pages_empty_state',
        sw_cms_layout_assignment_modal_product_detail_pages_select:
            'sw_cms_layout_assignment_modal_mt_product_detail_pages_select',
        sw_cms_layout_assignment_modal_shop_pages_select: 'sw_cms_layout_assignment_modal_mt_shop_pages_select',
        sw_cms_layout_assignment_modal_shop_pages_select_field: 'sw_cms_layout_assignment_modal_mt_shop_pages_select_field',
        sw_cms_layout_assignment_modal_shop_pages_select_sales_channel_field:
            'sw_cms_layout_assignment_modal_mt_shop_pages_select_sales_channel_field',
        sw_entity_many_to_many_assignment_card_select: 'sw_cms_layout_assignment_modal_mt_product_assignment_card_select',
    },
    'sw-custom-field-set-renderer': {
        sw_custom_field_set_renderer_card_form_renderer: 'sw_custom_field_set_renderer_card_mt_tabs_form_renderer',
    },
    'sw-custom-field-translated-labels': {
        sw_custom_field_translated_labels_translated_content_field:
            'sw_custom_field_translated_labels_translated_mt_content_field',
    },
    'sw-flow-rule-modal': {
        sw_flow_rule_modal_conditions_card: 'sw_flow_rule_modal_mt_conditions_card',
        sw_flow_rule_modal_detail_description: 'sw_flow_rule_modal_mt_detail_description',
        sw_flow_rule_modal_detail_name: 'sw_flow_rule_modal_mt_detail_name',
        sw_flow_rule_modal_detail_priority: 'sw_flow_rule_modal_mt_detail_priority',
        sw_flow_rule_modal_detail_type: 'sw_flow_rule_modal_mt_detail_type',
        sw_flow_rule_modal_tab_detail_content: 'sw_flow_rule_modal_mt_tab_detail_content',
        sw_flow_rule_modal_tab_rule_content: 'sw_flow_rule_modal_mt_tab_rule_content',
    },
    'sw-import-export-edit-profile-modal': {
        sw_import_export_edit_profile_modal_tabs_advanced: 'sw_import_export_edit_profile_modal_mt_tabs_advanced',
        sw_import_export_edit_profile_modal_tabs_advanced_identifiers:
            'sw_import_export_edit_profile_modal_mt_tabs_advanced_identifiers',
        sw_import_export_edit_profile_modal_tabs_advanced_text: 'sw_import_export_edit_profile_modal_mt_tabs_advanced_text',
        sw_import_export_edit_profile_modal_tabs_general_import_settings:
            'sw_import_export_edit_profile_modal_mt_tabs_general_import_settings',
        sw_import_export_edit_profile_modal_tabs_mappings: 'sw_import_export_edit_profile_modal_mt_tabs_mappings',
        sw_import_export_edit_profile_modal_tabs_mappings_mapping:
            'sw_import_export_edit_profile_modal_mt_tabs_mappings_mapping',
        sw_import_export_edit_profile_modal_tabs_mappings_text: 'sw_import_export_edit_profile_modal_mt_tabs_mappings_text',
    },
    'sw-media-modal-folder-settings': {
        sw_media_modal_folder_settings_default_folder: 'sw_media_modal_folder_settings_mt_default_folder',
        sw_media_modal_folder_settings_generate_thumbnails_field:
            'sw_media_modal_folder_settings_mt_generate_thumbnails_field',
        sw_media_modal_folder_settings_inherit_settings_field: 'sw_media_modal_folder_settings_mt_inherit_settings_field',
        sw_media_modal_folder_settings_keep_proportions_field: 'sw_media_modal_folder_settings_mt_keep_proportions_field',
        sw_media_modal_folder_settings_name_field: 'sw_media_modal_folder_settings_mt_name_field',
        sw_media_modal_folder_settings_tab_content_settings: 'sw_media_modal_folder_settings_mt_tab_content_settings',
        sw_media_modal_folder_settings_tab_content_thumbnails: 'sw_media_modal_folder_settings_mt_tab_content_thumbnails',
        sw_media_modal_folder_settings_tab_content_thumbnails_left_container:
            'sw_media_modal_folder_settings_mt_tab_content_thumbnails_left_container',
        sw_media_modal_folder_settings_tab_content_thumbnails_right_container:
            'sw_media_modal_folder_settings_mt_tab_content_thumbnails_right_container',
        sw_media_modal_folder_settings_thumbnail_list: 'sw_media_modal_folder_settings_mt_thumbnail_list',
        sw_media_modal_folder_settings_thumbnail_list_caption: 'sw_media_modal_folder_settings_mt_thumbnail_list_caption',
        sw_media_modal_folder_settings_thumbnail_list_container:
            'sw_media_modal_folder_settings_mt_thumbnail_list_container',
        sw_media_modal_folder_settings_thumbnail_size: 'sw_media_modal_folder_settings_mt_thumbnail_size',
        sw_media_modal_folder_settings_thumbnail_size_delete_button:
            'sw_media_modal_folder_settings_mt_thumbnail_size_delete_button',
        sw_media_modal_folder_settings_thumbnail_size_switch: 'sw_media_modal_folder_settings_mt_thumbnail_size_switch',
        sw_media_modal_folder_settings_thumbnails_quality_field:
            'sw_media_modal_folder_settings_mt_thumbnails_quality_field',
    },
    'sw-media-modal-v2': {
        sw_media_modal_v2_folder_breadcrumbs: 'sw_media_modal_v2_mt_folder_breadcrumbs',
        sw_media_modal_v2_media_library: 'sw_media_modal_v2_mt_media_library',
        sw_media_modal_v2_navigation_and_search: 'sw_media_modal_v2_mt_navigation_and_search',
        sw_media_modal_v2_search_field: 'sw_media_modal_v2_mt_search_field',
        sw_media_modal_v2_tab_content_library: 'sw_media_modal_v2_mt_tab_content_library',
        sw_media_modal_v2_tab_content_upload: 'sw_media_modal_v2_mt_tab_content_upload',
        sw_media_modal_v2_upload_component: 'sw_media_modal_v2_mt_upload_component',
        sw_media_modal_v2_uploaded_items: 'sw_media_modal_v2_mt_uploaded_items',
    },
    'sw-order-address-modal': {
        sw_order_address_modal_tabs_content_edit_address: 'sw_order_address_modal_mt_tabs_content_edit_address',
        sw_order_address_modal_tabs_content_select_address: 'sw_order_address_modal_mt_tabs_content_select_address',
    },
    'sw-order-create-initial-modal': {
        sw_order_create_modal_tabs_content: 'sw_order_create_modal_mt_tabs_content',
        sw_order_create_modal_tabs_content_customer: 'sw_order_create_modal_mt_tabs_content_customer',
        sw_order_create_modal_tabs_content_options: 'sw_order_create_modal_mt_tabs_content_options',
        sw_order_create_modal_tabs_content_products: 'sw_order_create_modal_mt_tabs_content_products',
    },
    'sw-order-new-customer-modal': {
        sw_order_new_customer_modal_content_billing_form: 'sw_order_new_customer_modal_mt_tabs_content_billing_form',
        sw_order_new_customer_modal_content_details_form: 'sw_order_new_customer_modal_mt_tabs_content_details_form',
        sw_order_new_customer_modal_content_details_guest: 'sw_order_new_customer_modal_mt_tabs_content_details_guest',
        sw_order_new_customer_modal_content_shipping: 'sw_order_new_customer_modal_mt_tabs_content_shipping',
        sw_order_new_customer_modal_content_shipping_form: 'sw_order_new_customer_modal_mt_tabs_content_shipping_form',
        sw_order_new_customer_modal_content_shipping_same_billing:
            'sw_order_new_customer_modal_mt_tabs_content_shipping_same_billing',
    },
    'sw-sales-channel-products-assignment-modal': {
        sw_sales_channel_products_assignment_modal_tab_content_categories:
            'sw_sales_channel_products_assignment_modal_mt_tab_content_categories',
        sw_sales_channel_products_assignment_modal_tab_content_dynamic_product_groups:
            'sw_sales_channel_products_assignment_modal_mt_tab_content_dynamic_product_groups',
        sw_sales_channel_products_assignment_modal_tab_content_single_products:
            'sw_sales_channel_products_assignment_modal_mt_tab_content_single_products',
    },
    'sw-settings-logging-entry-info': {
        sw_settings_logging_entry_info_content: 'sw_settings_logging_entry_info_mt_content',
        sw_settings_logging_entry_info_raw_content: 'sw_settings_logging_entry_info_mt_raw_content',
    },
    'sw-settings-logging-mail-sent-info': {
        sw_settings_logging_mail_sent_content_mailbody: 'sw_settings_logging_mail_sent_mt_content_mailbody',
        sw_settings_logging_mail_sent_content_mailbody_html: 'sw_settings_logging_mail_sent_mt_content_mailbody_html',
        sw_settings_logging_mail_sent_content_mailbody_plain: 'sw_settings_logging_mail_sent_mt_content_mailbody_plain',
        sw_settings_logging_mail_sent_content_mailbody_title: 'sw_settings_logging_mail_sent_mt_content_mailbody_title',
        sw_settings_logging_mail_sent_content_recipients: 'sw_settings_logging_mail_sent_mt_content_recipients',
        sw_settings_logging_mail_sent_content_recipients_title: 'sw_settings_logging_mail_sent_mt_content_recipients_title',
    },
    'sw-settings-tag-detail-modal': {
        sw_settings_tag_detail_modal_tabs_assignments_tab: 'sw_settings_tag_detail_modal_mt_tabs_assignments_tab',
        sw_settings_tag_detail_modal_tabs_general_tab: 'sw_settings_tag_detail_modal_mt_tabs_general_tab',
    },
};

/** The content blocks of the `sw-tabs` content slot, which passed its `active` slot variable to them. */
const TAB_CONTENT_BLOCKS = new Set([
    'sw_cms_el_form_config_content',
    'sw_cms_el_form_config_options',
    'sw_cms_el_product_description_rating_config_options',
    'sw_cms_el_text_config_content',
    'sw_cms_el_text_config_settings',
    'sw_cms_element_cross_selling_config_content',
    'sw_cms_element_cross_selling_config_options',
    'sw_cms_element_image_gallery_config_content',
    'sw_cms_element_image_gallery_config_settings',
    'sw_cms_element_image_slider_config_content',
    'sw_cms_element_image_slider_config_settings',
    'sw_cms_element_product_description_reviews_config_content',
    'sw_cms_element_product_slider_config_content',
    'sw_cms_element_product_slider_config_settings',
    'sw_cms_layout_assignment_modal_category_select',
    'sw_cms_layout_assignment_modal_landing_page_select',
    'sw_cms_layout_assignment_modal_shop_pages_select',
    'sw_flow_rule_modal_tab_detail_content',
    'sw_flow_rule_modal_tab_rule_content',
    'sw_media_modal_folder_settings_tab_content_settings',
    'sw_media_modal_folder_settings_tab_content_thumbnails',
    'sw_media_modal_v2_tab_content_library',
    'sw_media_modal_v2_tab_content_upload',
    'sw_order_create_modal_tabs_content',
    'sw_order_create_modal_tabs_content_customer',
    'sw_order_create_modal_tabs_content_options',
    'sw_order_create_modal_tabs_content_products',
    'sw_order_new_customer_modal_content_shipping',
    'sw_sales_channel_products_assignment_modal_tab_content_categories',
    'sw_sales_channel_products_assignment_modal_tab_content_dynamic_product_groups',
    'sw_sales_channel_products_assignment_modal_tab_content_single_products',
]);

export default templateBlockMigration({
    id: 'template-block.legacy-sw-tabs-content',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-tabs branches of these components were removed. The blocks of their tab contents ' +
        'live on in the mt-tabs branch, under a name with "mt" in it.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
    ],
    usage: renameBlocks(COUNTERPARTS, (from) => {
        return TAB_CONTENT_BLOCKS.has(from)
            ? {
                  removedSlotVariables: ['active'],
                  message: 'The mt-tabs branch has no "active" slot variable, use "activeTab" of the component instead.',
              }
            : {};
    }),
});
