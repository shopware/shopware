/**
 * @sw-package framework
 *
 * Twig blocks of the CMS element configs that 6.8 removes with the legacy sw-tabs branch of
 * `feature.isActive('v6.8.0.0')`, per core component.
 */

import { legacyTabs } from './removed-blocks';
import type { RemovedBlocks } from './removed-blocks';

export default [
    legacyTabs('sw-cms-el-config-buy-box', {
        sw_cms_element_buy_box_config_tab_content: { tab: 'tabs' },
        sw_cms_element_buy_box_config_tab_option: { tab: 'tabs' },
        sw_cms_element_buy_box_config_content_warning: 'sw_cms_element_buy_box_config_mt_content_warning',
        sw_cms_element_buy_box_config_product_select: 'sw_cms_element_buy_box_config_mt_product_select',
        sw_cms_element_buy_box_config_product_selection_label: 'sw_cms_element_buy_box_config_mt_product_variant_label',
        sw_cms_element_buy_box_config_product_select_result_item:
            'sw_cms_element_buy_box_config_mt_product_select_result_item',
        sw_cms_element_buy_box_config_product_select_result_item_inner:
            'sw_cms_element_buy_box_config_mt_product_select_result_label',
        sw_cms_element_buy_box_config_options: 'sw_cms_element_buy_box_config_mt_options',
    }),
    legacyTabs('sw-cms-el-config-cross-selling', {
        sw_cms_element_cross_selling_config_tab_content: { tab: 'tabs' },
        sw_cms_element_cross_selling_config_tab_options: { tab: 'tabs' },
        sw_cms_element_cross_selling_config_content: 'sw_cms_element_cross_selling_config_mt_content',
        sw_cms_element_cross_selling_config_content_warning_text:
            'sw_cms_element_cross_selling_config_mt_content_warning_text',
        sw_cms_element_cross_selling_config_content_products: 'sw_cms_element_cross_selling_config_mt_content_products',
        sw_cms_element_cross_selling_config_content_products_selection_label:
            'sw_cms_element_cross_selling_config_mt_product_variant_label',
        sw_cms_element_cross_selling_config_content_products_select_result_item:
            'sw_cms_element_cross_selling_config_mt_product_select_result_item',
        sw_cms_element_cross_selling_config_content_products_select_result_item_inner:
            'sw_cms_element_cross_selling_config_mt_product_select_result_label',
        sw_cms_element_cross_selling_config_options: 'sw_cms_element_cross_selling_config_mt_options',
        sw_cms_element_cross_selling_config_options_box_layout: 'sw_cms_element_cross_selling_config_mt_options_box_layout',
        sw_cms_element_cross_selling_config_options_display_mode:
            'sw_cms_element_cross_selling_config_mt_options_display_mode',
        sw_cms_element_cross_selling_config_options_min_width: 'sw_cms_element_cross_selling_config_mt_options_min_width',
        sw_cms_element_cross_selling_config_options_speed: 'sw_cms_element_cross_selling_config_mt_options_speed',
    }),
    legacyTabs('sw-cms-el-config-form', {
        sw_cms_el_config_form_tab_content: { tab: 'tabs' },
        sw_cms_el_form_config_tab_options: { tab: 'tabs' },
        sw_cms_el_form_config_content: 'sw_cms_el_form_config_mt_content',
        sw_cms_el_form_config_content_form_type: 'sw_cms_el_form_config_mt_content_form_type',
        sw_cms_el_form_config_content_form_title: 'sw_cms_el_form_config_mt_content_form_title',
        sw_cms_el_form_config_content_form_confirmation_text: 'sw_cms_el_form_config_mt_content_form_confirmation_text',
        sw_cms_el_form_config_options: 'sw_cms_el_form_config_mt_options',
    }),
    legacyTabs('sw-cms-el-config-image-gallery', {
        sw_cms_element_image_gallery_config_tab_content: { tab: 'tabs' },
        sw_cms_element_image_gallery_config_tab_options: { tab: 'tabs' },
        sw_cms_element_image_gallery_config_content: 'sw_cms_element_image_gallery_config_mt_content',
        sw_cms_element_image_gallery_config_media_selection: 'sw_cms_element_image_gallery_config_mt_media_selection',
        sw_cms_element_image_gallery_config_media_list_selection:
            'sw_cms_element_image_gallery_config_mt_media_list_selection',
        sw_cms_element_image_gallery_config_media_mapping_preview:
            'sw_cms_element_image_gallery_config_mt_media_mapping_preview',
        sw_cms_element_image_gallery_config_media_preview_list: 'sw_cms_element_image_gallery_config_mt_media_preview_list',
        sw_cms_element_image_gallery_config_media_preview_item: 'sw_cms_element_image_gallery_config_mt_media_preview_item',
        sw_cms_element_image_gallery_config_media_preview_info: 'sw_cms_element_image_gallery_config_mt_media_preview_info',
        sw_cms_element_image_gallery_config_media_upload_listener:
            'sw_cms_element_image_gallery_config_mt_media_upload_listener',
        sw_cms_element_image_gallery_config_media_modal: 'sw_cms_element_image_gallery_config_mt_media_modal',
        sw_cms_element_image_gallery_config_settings: 'sw_cms_element_image_gallery_config_mt_settings',
        sw_cms_element_image_gallery_config_settings_display_mode:
            'sw_cms_element_image_gallery_config_mt_settings_display_mode',
        sw_cms_element_image_gallery_config_settings_display_mode_select:
            'sw_cms_element_image_gallery_config_mt_settings_display_mode_select',
        sw_cms_element_image_gallery_config_settings_min_height:
            'sw_cms_element_image_gallery_config_mt_settings_min_height',
        sw_cms_element_image_gallery_config_settings_vertical_align:
            'sw_cms_element_image_gallery_config_mt_settings_vertical_align',
        sw_cms_element_image_gallery_config_settings_navigation:
            'sw_cms_element_image_gallery_config_mt_settings_navigation',
        sw_cms_element_image_gallery_config_settings_navigation_arrow_position:
            'sw_cms_element_image_gallery_config_mt_settings_navigation_arrow_position',
        sw_cms_element_image_gallery_config_settings_navigation_dots_position:
            'sw_cms_element_image_gallery_config_mt_settings_navigation_dots_position',
        sw_cms_element_image_gallery_config_settings_navigation_preview_position:
            'sw_cms_element_image_gallery_config_mt_settings_navigation_preview_position',
        sw_cms_element_image_gallery_config_settings_zoom_toggles:
            'sw_cms_element_image_gallery_config_mt_settings_zoom_toggles',
        sw_cms_element_image_gallery_config_settings_toggle_zoom:
            'sw_cms_element_image_gallery_config_mt_settings_toggle_zoom',
        sw_cms_element_image_gallery_config_settings_toggle_fullscreen:
            'sw_cms_element_image_gallery_config_mt_settings_toggle_fullscreen',
        sw_cms_element_image_gallery_config_settings_aspect_ratio_magnifier_over_gallery_toggles:
            'sw_cms_element_image_gallery_config_mt_settings_aspect_ratio_magnifier_over_gallery_toggles',
        sw_cms_element_image_gallery_config_settings_toggle_keep_aspect_ratio_on_zoom:
            'sw_cms_element_image_gallery_config_mt_settings_toggle_keep_aspect_ratio_on_zoom',
        sw_cms_element_image_gallery_config_settings_toggle_magnifier_over_gallery:
            'sw_cms_element_image_gallery_config_mt_settings_toggle_magnifier_over_gallery',
        sw_cms_element_image_gallery_config_settings_use_fetch_priority_on_first_item:
            'sw_cms_element_image_gallery_config_mt_settings_use_fetch_priority_on_first_item',
    }),
    legacyTabs('sw-cms-el-config-image-slider', {
        sw_cms_element_image_slider_config_tab_content: { tab: 'tabs' },
        sw_cms_element_image_slider_config_tab_options: { tab: 'tabs' },
        sw_cms_element_image_slider_config_content: 'sw_cms_element_image_slider_config_mt_content',
        sw_cms_element_image_slider_config_media_selection: 'sw_cms_element_image_slider_config_mt_media_selection',
        sw_cms_element_image_slider_config_media_upload_listener:
            'sw_cms_element_image_slider_config_mt_media_upload_listener',
        sw_cms_element_image_slider_config_media_modal: 'sw_cms_element_image_slider_config_mt_media_modal',
        sw_cms_element_image_slider_config_settings: 'sw_cms_element_image_slider_config_mt_settings',
        sw_cms_element_image_slider_config_settings_display_mode:
            'sw_cms_element_image_slider_config_mt_settings_display_mode',
        sw_cms_element_image_slider_config_settings_display_mode_select:
            'sw_cms_element_image_slider_config_mt_settings_display_mode_select',
        sw_cms_element_image_slider_config_settings_min_height: 'sw_cms_element_image_slider_config_mt_settings_min_height',
        sw_cms_element_image_slider_config_settings_vertical_align:
            'sw_cms_element_image_slider_config_mt_settings_vertical_align',
        sw_cms_element_image_slider_config_settings_navigation: 'sw_cms_element_image_slider_config_mt_settings_navigation',
        sw_cms_element_image_slider_config_settings_navigation_arrow_position:
            'sw_cms_element_image_slider_config_mt_settings_navigation_arrow_position',
        sw_cms_element_image_slider_config_settings_navigation_dots_position:
            'sw_cms_element_image_slider_config_mt_settings_navigation_dots_position',
        sw_cms_element_image_slider_config_settings_speed: 'sw_cms_element_image_slider_config_mt_settings_speed',
        sw_cms_element_image_slider_config_settings_auto_slide: 'sw_cms_element_image_slider_config_mt_settings_auto_slide',
        sw_cms_element_image_slider_config_settings_autoplay_timeout:
            'sw_cms_element_image_slider_config_mt_settings_autoplay_timeout',
        sw_cms_element_image_slider_config_settings_use_fetch_priority_on_first_item:
            'sw_cms_element_image_slider_config_mt_settings_use_fetch_priority_on_first_item',
        sw_cms_element_image_slider_config_settings_links: 'sw_cms_element_image_slider_config_mt_settings_links',
        sw_cms_element_image_slider_config_settings_link_url: 'sw_cms_element_image_slider_config_mt_settings_link_url',
        sw_cms_element_image_slider_config_settings_link_aria_label:
            'sw_cms_element_image_slider_config_mt_settings_link_aria_label',
        sw_cms_element_image_slider_config_settings_link_target:
            'sw_cms_element_image_slider_config_mt_settings_link_target',
    }),
    legacyTabs('sw-cms-el-config-product-description-reviews', {
        sw_cms_element_product_description_reviews_config_tab_content: { tab: 'tabs' },
        sw_cms_element_product_description_reviews_config_tab_options: { tab: 'tabs' },
        sw_cms_element_product_description_reviews_config_content:
            'sw_cms_element_product_description_reviews_config_mt_content',
        sw_cms_element_product_description_reviews_warning: 'sw_cms_element_product_description_reviews_mt_warning',
        sw_cms_element_product_description_reviews_config_product_select:
            'sw_cms_element_product_description_reviews_config_mt_product_select',
        sw_cms_element_product_description_reviews_config_product_selection_label:
            'sw_cms_element_product_description_reviews_config_mt_product_variant_label',
        sw_cms_element_product_description_reviews_config_product_select_result_item:
            'sw_cms_element_product_description_reviews_config_mt_product_select_result_item',
        sw_cms_element_product_description_reviews_config_product_select_result_item_inner:
            'sw_cms_element_product_description_reviews_config_mt_product_select_result_label',
        sw_cms_el_product_description_rating_config_options: 'sw_cms_el_product_description_rating_config_mt_options',
        sw_cms_el_product_description_rating_config_options_alignment:
            'sw_cms_el_product_description_rating_config_mt_options_alignment',
    }),
    legacyTabs('sw-cms-el-config-product-listing', {
        sw_cms_element_product_listing_config_layout_select: 'sw_cms_element_product_listing_config_mt_layout_select',
        sw_cms_element_product_listing_config_info: 'sw_cms_element_product_listing_config_mt_info',
        sw_cms_element_product_listing_config_show_sorting: 'sw_cms_element_product_listing_config_mt_show_sorting',
        sw_cms_element_product_listing_config_use_default_sorting:
            'sw_cms_element_product_listing_config_mt_use_default_sorting',
        sw_cms_element_product_listing_config_default_sorting: 'sw_cms_element_product_listing_config_mt_default_sorting',
        sw_cms_element_product_listing_config_available_sortings:
            'sw_cms_element_product_listing_config_mt_available_sortings',
        sw_entity_multi_select_base_results_list_result_label:
            'sw_cms_element_product_listing_config_mt_available_sortings_result_label',
        sw_cms_element_product_listing_config_sorting_grid: 'sw_cms_element_product_listing_config_mt_sorting_grid',
        sw_cms_element_product_listing_config_filter_info: 'sw_cms_element_product_listing_config_mt_filter_info',
        sw_cms_element_product_listing_config_filter_by_wrapper:
            'sw_cms_element_product_listing_config_mt_filter_by_wrapper',
        sw_cms_element_product_listing_config_filter_by_manufacturer:
            'sw_cms_element_product_listing_config_mt_filter_by_manufacturer',
        sw_cms_element_product_listing_config_filter_by_rating: 'sw_cms_element_product_listing_config_mt_filter_by_rating',
        sw_cms_element_product_listing_config_filter_by_price: 'sw_cms_element_product_listing_config_mt_filter_by_price',
        sw_cms_element_product_listing_config_filter_for_free_shipping:
            'sw_cms_element_product_listing_config_mt_filter_for_free_shipping',
        sw_cms_element_product_listing_config_filter_properties_wrapper:
            'sw_cms_element_product_listing_config_mt_filter_properties_wrapper',
        sw_cms_element_product_listing_config_filter_spacer: 'sw_cms_element_product_listing_config_mt_filter_spacer',
        sw_cms_element_product_listing_config_filter_properties_as_filter:
            'sw_cms_element_product_listing_config_mt_filter_properties_as_filter',
        sw_cms_element_product_listing_config_filter_properties_as_filter_switch:
            'sw_cms_element_product_listing_config_mt_filter_properties_as_filter_switch',
        sw_cms_element_product_listing_config_filter_properties_as_filter_info_text:
            'sw_cms_element_product_listing_config_mt_filter_properties_as_filter_info_text',
        sw_cms_element_product_listing_config_filter_property_search:
            'sw_cms_element_product_listing_config_mt_filter_property_search',
        sw_cms_element_product_listing_config_filter_property_grid:
            'sw_cms_element_product_listing_config_mt_filter_property_grid',
        sw_cms_element_product_listing_config_filter_property_grid_columns:
            'sw_cms_element_product_listing_config_mt_filter_property_grid_columns',
        sw_cms_element_product_listing_config_filter_property_grid_column_status:
            'sw_cms_element_product_listing_config_mt_filter_property_grid_column_status',
        sw_cms_element_product_listing_config_filter_property_grid_pagination:
            'sw_cms_element_product_listing_config_mt_filter_property_grid_pagination',
        sw_cms_element_product_listing_config_filter_empty_state:
            'sw_cms_element_product_listing_config_mt_filter_empty_state',
    }),
    legacyTabs('sw-cms-el-config-product-slider', {
        sw_cms_element_product_slider_config_tab_content: { tab: 'tabs' },
        sw_cms_element_product_slider_config_tab_options: { tab: 'tabs' },
        sw_cms_element_product_slider_config_content: 'sw_cms_element_product_slider_config_mt_content',
        sw_cms_element_product_slider_config_content_title: 'sw_cms_element_product_slider_config_mt_content_title',
        sw_cms_element_product_slider_config_content_product_assignment_type:
            'sw_cms_element_product_slider_config_mt_content_product_assignment_type',
        sw_cms_element_product_slider_config_content_product_stream_select:
            'sw_cms_element_product_slider_config_mt_content_product_stream_select',
        sw_cms_element_product_slider_config_content_product_stream_performance_hint:
            'sw_cms_element_product_slider_config_mt_content_product_stream_performance_hint',
        sw_cms_element_product_slider_config_content_product_stream_sorting:
            'sw_cms_element_product_slider_config_mt_content_product_stream_sorting',
        sw_cms_element_product_slider_config_content_product_stream_limit:
            'sw_cms_element_product_slider_config_mt_content_product_stream_limit',
        sw_cms_element_product_slider_config_content_product_stream_preview_link:
            'sw_cms_element_product_slider_config_mt_content_product_stream_preview_link',
        sw_cms_element_product_slider_config_content_products: 'sw_cms_element_product_slider_config_mt_content_products',
        sw_cms_element_product_slider_config_content_products_selection_label: {
            extend: 'sw_cms_element_product_slider_config_mt_content_products',
        },
        sw_cms_element_product_slider_config_content_products_select_result_item: {
            extend: 'sw_cms_element_product_slider_config_mt_content_products',
        },
        sw_cms_element_product_slider_config_content_products_select_result_item_inner:
            'sw_cms_element_product_slider_config_mt_entity_single_select_base_results_list_result_label',
        sw_cms_element_product_slider_config_settings: 'sw_cms_element_product_slider_config_mt_settings',
        sw_cms_element_product_slider_config_settings_display_mode:
            'sw_cms_element_product_slider_config_mt_settings_display_mode',
        sw_cms_element_product_slider_config_settings_min_width:
            'sw_cms_element_product_slider_config_mt_settings_min_width',
        sw_cms_element_product_slider_config_settings_vertical_align:
            'sw_cms_element_product_slider_config_mt_settings_vertical_align',
        sw_cms_element_product_slider_config_settings_box_layout:
            'sw_cms_element_product_slider_config_mt_settings_box_layout',
        sw_cms_element_product_slider_config_settings_border: 'sw_cms_element_product_slider_config_mt_settings_border',
        sw_cms_element_product_slider_config_settings_navigation_arrows:
            'sw_cms_element_product_slider_config_mt_settings_navigation_arrows',
        sw_cms_element_product_slider_config_settings_speed: 'sw_cms_element_product_slider_config_mt_settings_speed',
        sw_cms_element_product_slider_config_settings_rotate: 'sw_cms_element_product_slider_config_mt_settings_rotate',
        sw_cms_element_product_slider_config_settings_autoplay_timeout:
            'sw_cms_element_product_slider_config_mt_settings_autoplay_timeout',
    }),
    legacyTabs(
        'sw-cms-el-config-text',
        {
            sw_cms_el_config_text_tab_content: { tab: 'tabs' },
            sw_cms_el_text_config_tab_options: { tab: 'tabs' },
            sw_cms_el_text_config_content: 'sw_cms_el_text_config_mt_content',
            sw_cms_el_text_config_settings: 'sw_cms_el_text_config_mt_settings',
            sw_cms_el_text_config_settings_vertical_align: 'sw_cms_el_text_config_mt_settings_vertical_align',
        },
        ['sw-cms-el-config-category-name', 'sw-cms-el-config-product-name'],
    ),
    legacyTabs('sw-cms-layout-assignment-modal', {
        sw_cms_layout_assignment_modal_tab_categories: { tab: 'layoutAssignmentTabs' },
        sw_cms_layout_assignment_modal_tab_shop_pages: { tab: 'layoutAssignmentTabs' },
        sw_cms_layout_assignment_modal_tab_landing_pages: { tab: 'layoutAssignmentTabs' },
        sw_cms_layout_assignment_modal_landing_page_select: 'sw_cms_layout_assignment_modal_mt_landing_page_select',
        sw_cms_layout_assignment_modal_landing_page_select_field:
            'sw_cms_layout_assignment_modal_mt_landing_page_select_field',
        sw_cms_layout_assignment_modal_category_select: 'sw_cms_layout_assignment_modal_mt_category_select',
        sw_cms_layout_assignment_modal_category_select_field: 'sw_cms_layout_assignment_modal_mt_category_select_field',
        sw_cms_layout_assignment_modal_shop_pages_select: 'sw_cms_layout_assignment_modal_mt_shop_pages_select',
        sw_cms_layout_assignment_modal_shop_pages_select_sales_channel_field:
            'sw_cms_layout_assignment_modal_mt_shop_pages_select_sales_channel_field',
        sw_cms_layout_assignment_modal_shop_pages_select_field: 'sw_cms_layout_assignment_modal_mt_shop_pages_select_field',
        sw_cms_layout_assignment_modal_product_detail_pages_select:
            'sw_cms_layout_assignment_modal_mt_product_detail_pages_select',
        sw_entity_many_to_many_assignment_card_select: 'sw_cms_layout_assignment_modal_mt_product_assignment_card_select',
        sw_cms_layout_assignment_modal_product_detail_pages_column_name:
            'sw_cms_layout_assignment_modal_mt_product_detail_pages_column_name',
        sw_cms_layout_assignment_modal_product_detail_pages_column_manufacturer:
            'sw_cms_layout_assignment_modal_mt_product_detail_pages_column_manufacturer',
        sw_cms_layout_assignment_modal_product_detail_pages_empty_state:
            'sw_cms_layout_assignment_modal_mt_product_detail_pages_empty_state',
    }),
] satisfies RemovedBlocks[];
