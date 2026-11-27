/**
 * @sw-package framework
 *
 * Twig blocks of core Administration templates (and the Administration part of the Storefront) that 6.8 renames.
 * Extracted from the `@deprecated tag:v6.8.0 - … Use <block> instead` markers, then checked by hand.
 *
 * - `to`: the codemod renames the override. Only for renames where both names render on 6.7.17 and the new name
 *   stays in 6.8.
 * - `use`, `in`, `tab`, `hint`: no name renders on 6.7 and 6.8, so the codemod writes a comment with the 6.8 target:
 *   the block to override (in another component with `in`), the mt-tabs `items` computed that takes the tab, or a
 *   sentence of its own.
 * - `flag`: the 6.7 version from which the 6.8 target renders with the `v6.8.0.0` feature flag.
 * - `shared`: other core components use the name as well. The codemod only checks it when it knows which component
 *   a template overrides.
 */

const MT = '6.7.14';

module.exports = [
    // Typos and misleading names
    { component: 'sw-condition-date-range', from: 'sw_condiiton_date_range_field_to_date', to: 'sw_condition_date_range_field_to_date' },
    { component: 'sw-cms-detail', from: 'sw_cms_detail_stage_empty_stade_content', to: 'sw_cms_detail_stage_empty_stage_content' },
    { component: 'sw-settings-listing-option-base', from: 'sw_settings_listing_option_base_smart_content', to: 'sw_settings_listing_option_base_content' },
    { component: 'sw-settings-listing-option-base', from: 'sw_settings_listing_option_base_smart_content_general_info', to: 'sw_settings_listing_option_base_content_general_info' },
    { component: 'sw-settings-listing-option-base', from: 'sw_settings_listing_option_base_smart_bar_actions_grid', to: 'sw_settings_listing_option_base_content_criteria_grid' },
    { component: 'sw-settings-listing-option-base', from: 'sw_settings_listing_option_base_smart_bar_actions_grid_delete_modal', to: 'sw_settings_listing_option_base_content_delete_modal' },
    { component: 'sw-cms-el-config-product-box', from: 'sw_entity_single_select_base_results_list_result_label', to: 'sw_cms_element_product_box_config_product_select_result_item_inner', shared: true },
    { component: 'sw-product-cross-selling-assignment', from: 'sw_entity_single_select_base_results_list_result_label', to: 'sw_product_cross_selling_assignment_select_result_item_inner', shared: true },
    { component: 'sw-settings-customer-group-detail', from: 'sw_settings_customer_group_detail_content_card_display_gross', to: 'sw_settings_customer_group_detail_content_card_tax_display' },
    { component: 'sw-product-stream-filter', from: 'sw_product_stream_filter', to: 'sw_condition_base' },
    { component: 'sw-product-stream-filter', from: 'sw_product_stream_filter_container', to: 'sw_condition_base_content' },

    // CMS element configs: the 6.7 target sits in the legacy sw-tabs branch, so only the `_mt_` block stays in 6.8
    { component: 'sw-cms-el-config-buy-box', from: 'sw_cms_element_buy_box_config_product_variant_label', use: 'sw_cms_element_buy_box_config_mt_product_variant_label', flag: MT },
    { component: 'sw-cms-el-config-buy-box', from: 'sw_entity_single_select_base_results_list_result_label', use: 'sw_cms_element_buy_box_config_mt_product_select_result_label', flag: MT, shared: true },
    { component: 'sw-cms-el-config-cross-selling', from: 'sw_entity_single_select_variant_selected_item', use: 'sw_cms_element_cross_selling_config_mt_product_variant_label', flag: MT, shared: true },
    { component: 'sw-cms-el-config-cross-selling', from: 'sw_entity_single_select_variant_result_item', use: 'sw_cms_element_cross_selling_config_mt_product_select_result_item', flag: MT, shared: true },
    { component: 'sw-cms-el-config-cross-selling', from: 'sw_entity_single_select_base_results_list_result_label', use: 'sw_cms_element_cross_selling_config_mt_product_select_result_label', flag: MT, shared: true },
    { component: 'sw-cms-el-config-product-description-reviews', from: 'sw_entity_single_select_variant_selected_item', use: 'sw_cms_element_product_description_reviews_config_mt_product_variant_label', flag: MT, shared: true },
    { component: 'sw-cms-el-config-product-description-reviews', from: 'sw_entity_single_select_variant_result_item', use: 'sw_cms_element_product_description_reviews_config_mt_product_select_result_item', flag: MT, shared: true },
    { component: 'sw-cms-el-config-product-description-reviews', from: 'sw_entity_single_select_base_results_list_result_label', use: 'sw_cms_element_product_description_reviews_config_mt_product_select_result_label', flag: MT, shared: true },
    { component: 'sw-cms-el-config-product-slider', from: 'sw_entity_single_select_base_results_list_result_label', use: 'sw_cms_element_product_slider_config_mt_entity_single_select_base_results_list_result_label', flag: MT, shared: true },
    { component: 'sw-cms-el-config-image-slider', from: 'sw_cms_element_image_gallery_config_settings_display_mode', use: 'sw_cms_element_image_slider_config_mt_settings_display_mode', flag: MT, shared: true },
    { component: 'sw-cms-el-config-image-slider', from: 'sw_cms_element_image_gallery_config_settings_display_mode_select', use: 'sw_cms_element_image_slider_config_mt_settings_display_mode_select', flag: MT, shared: true },
    { component: 'sw-cms-el-config-image-slider', from: 'sw_cms_element_image_gallery_config_settings_min_height', use: 'sw_cms_element_image_slider_config_mt_settings_min_height', flag: MT, shared: true },
    { component: 'sw-cms-el-config-image-slider', from: 'sw_cms_element_image_gallery_config_settings_vertical_align', use: 'sw_cms_element_image_slider_config_mt_settings_vertical_align', flag: MT, shared: true },

    // Other areas
    { component: 'sw-sales-channel-detail', from: 'sw_sales_channel_detail_content_tab_product_comparison', tab: 'salesChannelDetailTabs', flag: MT },
    { component: 'sw-settings-storefront-index', from: 'sw_settings_storefront_smtp_settings', hint: 'Override sw_settings_storefront_settings_icon_cache and sw_settings_storefront_settings_speculation_rules instead.' },
    { component: 'sw-theme-manager-detail', from: 'sw_theme_manager_detail_sidebar', use: 'sw_theme_manager_detail_media_modal' },
    { component: 'sw-theme-manager-detail', from: 'sw_theme_manager_detail_sidebar_media', use: 'sw_theme_manager_detail_media_modal' },
    { component: 'sw-theme-manager-detail', from: 'sw_theme_manager_detail_sidebar_media_items', use: 'sw_theme_manager_detail_media_modal' },
    { component: 'sw-mail-template-detail', from: 'sw_mail_template_detail_preview_modal_footer', use: 'sw_mail_template_detail_preview_modal_footer', in: 'sw-mail-template-preview-modal', flag: '6.7.11', shared: true },
    { component: 'sw-mail-template-detail', from: 'sw_mail_template_detail_preview_modal_footer_cancel', use: 'sw_mail_template_detail_preview_modal_footer_cancel', in: 'sw-mail-template-preview-modal', flag: '6.7.11', shared: true },
];
