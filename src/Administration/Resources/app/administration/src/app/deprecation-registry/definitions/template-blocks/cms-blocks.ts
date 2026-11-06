/**
 * @sw-package framework
 */

import { removeTemplateBlocks, renameTemplateBlocks, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.cms-6-8',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of the CMS module that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#renamed-cms-element-config-blocks'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
    ],
    usage: [
        ...renameTemplateBlocks({
            components: ['sw-cms-el-config-buy-box'],
            sharedNames: ['sw_entity_single_select_base_results_list_result_label'],
            blocks: {
                sw_cms_element_buy_box_config_content_warning: 'sw_cms_element_buy_box_config_mt_content_warning',
                sw_cms_element_buy_box_config_options: 'sw_cms_element_buy_box_config_mt_options',
                sw_cms_element_buy_box_config_product_select: 'sw_cms_element_buy_box_config_mt_product_select',
                sw_cms_element_buy_box_config_product_select_result_item:
                    'sw_cms_element_buy_box_config_mt_product_select_result_item',
                sw_cms_element_buy_box_config_product_select_result_item_inner:
                    'sw_cms_element_buy_box_config_mt_product_select_result_label',
                sw_cms_element_buy_box_config_product_selection_label:
                    'sw_cms_element_buy_box_config_mt_product_variant_label',
                sw_cms_element_buy_box_config_product_variant_label:
                    'sw_cms_element_buy_box_config_mt_product_variant_label',
                sw_entity_single_select_base_results_list_result_label:
                    'sw_cms_element_buy_box_config_mt_product_select_result_label',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-cms-el-config-buy-box'],
            blocks: {
                sw_cms_element_buy_box_config_tab_content:
                    'sw_cms_element_buy_box_config_mt_tabs and add your tab to its tabs computed',
                sw_cms_element_buy_box_config_tab_option:
                    'sw_cms_element_buy_box_config_mt_tabs and add your tab to its tabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-cms-el-config-cross-selling'],
            sharedNames: [
                'sw_entity_single_select_base_results_list_result_label',
                'sw_entity_single_select_variant_result_item',
                'sw_entity_single_select_variant_selected_item',
            ],
            activeSlot: ['sw_cms_element_cross_selling_config_content', 'sw_cms_element_cross_selling_config_options'],
            blocks: {
                sw_cms_element_cross_selling_config_content: 'sw_cms_element_cross_selling_config_mt_content',
                sw_cms_element_cross_selling_config_content_products:
                    'sw_cms_element_cross_selling_config_mt_content_products',
                sw_cms_element_cross_selling_config_content_products_select_result_item:
                    'sw_cms_element_cross_selling_config_mt_product_select_result_item',
                sw_cms_element_cross_selling_config_content_products_select_result_item_inner:
                    'sw_cms_element_cross_selling_config_mt_product_select_result_label',
                sw_cms_element_cross_selling_config_content_products_selection_label:
                    'sw_cms_element_cross_selling_config_mt_product_variant_label',
                sw_cms_element_cross_selling_config_content_warning_text:
                    'sw_cms_element_cross_selling_config_mt_content_warning_text',
                sw_cms_element_cross_selling_config_options: 'sw_cms_element_cross_selling_config_mt_options',
                sw_cms_element_cross_selling_config_options_box_layout:
                    'sw_cms_element_cross_selling_config_mt_options_box_layout',
                sw_cms_element_cross_selling_config_options_display_mode:
                    'sw_cms_element_cross_selling_config_mt_options_display_mode',
                sw_cms_element_cross_selling_config_options_min_width:
                    'sw_cms_element_cross_selling_config_mt_options_min_width',
                sw_cms_element_cross_selling_config_options_speed: 'sw_cms_element_cross_selling_config_mt_options_speed',
                sw_entity_single_select_base_results_list_result_label:
                    'sw_cms_element_cross_selling_config_mt_product_select_result_label',
                sw_entity_single_select_variant_result_item:
                    'sw_cms_element_cross_selling_config_mt_product_select_result_item',
                sw_entity_single_select_variant_selected_item:
                    'sw_cms_element_cross_selling_config_mt_product_variant_label',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-cms-el-config-cross-selling'],
            blocks: {
                sw_cms_element_cross_selling_config_tab_content:
                    'sw_cms_element_cross_selling_config_mt_tabs and add your tab to its tabs computed',
                sw_cms_element_cross_selling_config_tab_options:
                    'sw_cms_element_cross_selling_config_mt_tabs and add your tab to its tabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-cms-el-config-form'],
            activeSlot: ['sw_cms_el_form_config_content', 'sw_cms_el_form_config_options'],
            blocks: {
                sw_cms_el_form_config_content: 'sw_cms_el_form_config_mt_content',
                sw_cms_el_form_config_content_form_confirmation_text:
                    'sw_cms_el_form_config_mt_content_form_confirmation_text',
                sw_cms_el_form_config_content_form_title: 'sw_cms_el_form_config_mt_content_form_title',
                sw_cms_el_form_config_content_form_type: 'sw_cms_el_form_config_mt_content_form_type',
                sw_cms_el_form_config_options: 'sw_cms_el_form_config_mt_options',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-cms-el-config-form'],
            blocks: {
                sw_cms_el_config_form_tab_content: 'sw_cms_el_config_form_mt_tabs and add your tab to its tabs computed',
                sw_cms_el_form_config_tab_options: 'sw_cms_el_config_form_mt_tabs and add your tab to its tabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-cms-el-config-image-gallery'],
            sharedNames: [
                'sw_cms_element_image_gallery_config_settings_display_mode',
                'sw_cms_element_image_gallery_config_settings_display_mode_select',
                'sw_cms_element_image_gallery_config_settings_min_height',
                'sw_cms_element_image_gallery_config_settings_vertical_align',
            ],
            activeSlot: ['sw_cms_element_image_gallery_config_content', 'sw_cms_element_image_gallery_config_settings'],
            blocks: {
                sw_cms_element_image_gallery_config_content: 'sw_cms_element_image_gallery_config_mt_content',
                sw_cms_element_image_gallery_config_media_list_selection:
                    'sw_cms_element_image_gallery_config_mt_media_list_selection',
                sw_cms_element_image_gallery_config_media_mapping_preview:
                    'sw_cms_element_image_gallery_config_mt_media_mapping_preview',
                sw_cms_element_image_gallery_config_media_modal: 'sw_cms_element_image_gallery_config_mt_media_modal',
                sw_cms_element_image_gallery_config_media_preview_info:
                    'sw_cms_element_image_gallery_config_mt_media_preview_info',
                sw_cms_element_image_gallery_config_media_preview_item:
                    'sw_cms_element_image_gallery_config_mt_media_preview_item',
                sw_cms_element_image_gallery_config_media_preview_list:
                    'sw_cms_element_image_gallery_config_mt_media_preview_list',
                sw_cms_element_image_gallery_config_media_selection:
                    'sw_cms_element_image_gallery_config_mt_media_selection',
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
        }),
        ...removeTemplateBlocks({
            components: ['sw-cms-el-config-image-gallery'],
            blocks: {
                sw_cms_element_image_gallery_config_tab_content:
                    'sw_cms_element_image_gallery_config_mt_tabs and add your tab to its tabs computed',
                sw_cms_element_image_gallery_config_tab_options:
                    'sw_cms_element_image_gallery_config_mt_tabs and add your tab to its tabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-cms-el-config-image-slider'],
            sharedNames: [
                'sw_cms_element_image_gallery_config_settings_display_mode',
                'sw_cms_element_image_gallery_config_settings_display_mode_select',
                'sw_cms_element_image_gallery_config_settings_min_height',
                'sw_cms_element_image_gallery_config_settings_vertical_align',
            ],
            activeSlot: ['sw_cms_element_image_slider_config_content', 'sw_cms_element_image_slider_config_settings'],
            blocks: {
                sw_cms_element_image_gallery_config_settings_display_mode:
                    'sw_cms_element_image_slider_config_mt_settings_display_mode',
                sw_cms_element_image_gallery_config_settings_display_mode_select:
                    'sw_cms_element_image_slider_config_mt_settings_display_mode_select',
                sw_cms_element_image_gallery_config_settings_min_height:
                    'sw_cms_element_image_slider_config_mt_settings_min_height',
                sw_cms_element_image_gallery_config_settings_vertical_align:
                    'sw_cms_element_image_slider_config_mt_settings_vertical_align',
                sw_cms_element_image_slider_config_content: 'sw_cms_element_image_slider_config_mt_content',
                sw_cms_element_image_slider_config_media_modal: 'sw_cms_element_image_slider_config_mt_media_modal',
                sw_cms_element_image_slider_config_media_selection: 'sw_cms_element_image_slider_config_mt_media_selection',
                sw_cms_element_image_slider_config_media_upload_listener:
                    'sw_cms_element_image_slider_config_mt_media_upload_listener',
                sw_cms_element_image_slider_config_settings: 'sw_cms_element_image_slider_config_mt_settings',
                sw_cms_element_image_slider_config_settings_auto_slide:
                    'sw_cms_element_image_slider_config_mt_settings_auto_slide',
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
                sw_cms_element_image_slider_config_settings_link_url:
                    'sw_cms_element_image_slider_config_mt_settings_link_url',
                sw_cms_element_image_slider_config_settings_links: 'sw_cms_element_image_slider_config_mt_settings_links',
                sw_cms_element_image_slider_config_settings_min_height:
                    'sw_cms_element_image_slider_config_mt_settings_min_height',
                sw_cms_element_image_slider_config_settings_navigation:
                    'sw_cms_element_image_slider_config_mt_settings_navigation',
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
        }),
        ...removeTemplateBlocks({
            components: ['sw-cms-el-config-image-slider'],
            blocks: {
                sw_cms_element_image_slider_config_tab_content:
                    'sw_cms_element_image_slider_config_mt_tabs and add your tab to its tabs computed',
                sw_cms_element_image_slider_config_tab_options:
                    'sw_cms_element_image_slider_config_mt_tabs and add your tab to its tabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-cms-el-config-product-box'],
            sharedNames: ['sw_entity_single_select_base_results_list_result_label'],
            blocks: {
                sw_entity_single_select_base_results_list_result_label:
                    'sw_cms_element_product_box_config_product_select_result_item_inner',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-cms-el-config-product-description-reviews'],
            sharedNames: [
                'sw_entity_single_select_base_results_list_result_label',
                'sw_entity_single_select_variant_result_item',
                'sw_entity_single_select_variant_selected_item',
            ],
            activeSlot: [
                'sw_cms_el_product_description_rating_config_options',
                'sw_cms_element_product_description_reviews_config_content',
            ],
            blocks: {
                sw_cms_el_product_description_rating_config_options:
                    'sw_cms_el_product_description_rating_config_mt_options',
                sw_cms_el_product_description_rating_config_options_alignment:
                    'sw_cms_el_product_description_rating_config_mt_options_alignment',
                sw_cms_element_product_description_reviews_config_content:
                    'sw_cms_element_product_description_reviews_config_mt_content',
                sw_cms_element_product_description_reviews_config_product_select:
                    'sw_cms_element_product_description_reviews_config_mt_product_select',
                sw_cms_element_product_description_reviews_config_product_select_result_item:
                    'sw_cms_element_product_description_reviews_config_mt_product_select_result_item',
                sw_cms_element_product_description_reviews_config_product_select_result_item_inner:
                    'sw_cms_element_product_description_reviews_config_mt_product_select_result_label',
                sw_cms_element_product_description_reviews_config_product_selection_label:
                    'sw_cms_element_product_description_reviews_config_mt_product_variant_label',
                sw_cms_element_product_description_reviews_warning: 'sw_cms_element_product_description_reviews_mt_warning',
                sw_entity_single_select_base_results_list_result_label:
                    'sw_cms_element_product_description_reviews_config_mt_product_select_result_label',
                sw_entity_single_select_variant_result_item:
                    'sw_cms_element_product_description_reviews_config_mt_product_select_result_item',
                sw_entity_single_select_variant_selected_item:
                    'sw_cms_element_product_description_reviews_config_mt_product_variant_label',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-cms-el-config-product-description-reviews'],
            blocks: {
                sw_cms_element_product_description_reviews_config_tab_content:
                    'sw_cms_element_product_description_reviews_config_mt_tabs and add your tab to its tabs computed',
                sw_cms_element_product_description_reviews_config_tab_options:
                    'sw_cms_element_product_description_reviews_config_mt_tabs and add your tab to its tabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-cms-el-config-product-listing'],
            sharedNames: ['sw_entity_multi_select_base_results_list_result_label'],
            blocks: {
                sw_cms_element_product_listing_config_available_sortings:
                    'sw_cms_element_product_listing_config_mt_available_sortings',
                sw_cms_element_product_listing_config_default_sorting:
                    'sw_cms_element_product_listing_config_mt_default_sorting',
                sw_cms_element_product_listing_config_filter_by_manufacturer:
                    'sw_cms_element_product_listing_config_mt_filter_by_manufacturer',
                sw_cms_element_product_listing_config_filter_by_price:
                    'sw_cms_element_product_listing_config_mt_filter_by_price',
                sw_cms_element_product_listing_config_filter_by_rating:
                    'sw_cms_element_product_listing_config_mt_filter_by_rating',
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
                sw_cms_element_product_listing_config_filter_spacer:
                    'sw_cms_element_product_listing_config_mt_filter_spacer',
                sw_cms_element_product_listing_config_info: 'sw_cms_element_product_listing_config_mt_info',
                sw_cms_element_product_listing_config_layout_select:
                    'sw_cms_element_product_listing_config_mt_layout_select',
                sw_cms_element_product_listing_config_show_sorting: 'sw_cms_element_product_listing_config_mt_show_sorting',
                sw_cms_element_product_listing_config_sorting_grid: 'sw_cms_element_product_listing_config_mt_sorting_grid',
                sw_cms_element_product_listing_config_use_default_sorting:
                    'sw_cms_element_product_listing_config_mt_use_default_sorting',
                sw_entity_multi_select_base_results_list_result_label:
                    'sw_cms_element_product_listing_config_mt_available_sortings_result_label',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-cms-el-config-product-slider'],
            sharedNames: ['sw_entity_single_select_base_results_list_result_label'],
            activeSlot: ['sw_cms_element_product_slider_config_content', 'sw_cms_element_product_slider_config_settings'],
            blocks: {
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
                sw_cms_element_product_slider_config_content_products:
                    'sw_cms_element_product_slider_config_mt_content_products',
                sw_cms_element_product_slider_config_content_products_select_result_item_inner:
                    'sw_cms_element_product_slider_config_mt_entity_single_select_base_results_list_result_label',
                sw_cms_element_product_slider_config_content_title: 'sw_cms_element_product_slider_config_mt_content_title',
                sw_cms_element_product_slider_config_settings: 'sw_cms_element_product_slider_config_mt_settings',
                sw_cms_element_product_slider_config_settings_autoplay_timeout:
                    'sw_cms_element_product_slider_config_mt_settings_autoplay_timeout',
                sw_cms_element_product_slider_config_settings_border:
                    'sw_cms_element_product_slider_config_mt_settings_border',
                sw_cms_element_product_slider_config_settings_box_layout:
                    'sw_cms_element_product_slider_config_mt_settings_box_layout',
                sw_cms_element_product_slider_config_settings_display_mode:
                    'sw_cms_element_product_slider_config_mt_settings_display_mode',
                sw_cms_element_product_slider_config_settings_min_width:
                    'sw_cms_element_product_slider_config_mt_settings_min_width',
                sw_cms_element_product_slider_config_settings_navigation_arrows:
                    'sw_cms_element_product_slider_config_mt_settings_navigation_arrows',
                sw_cms_element_product_slider_config_settings_rotate:
                    'sw_cms_element_product_slider_config_mt_settings_rotate',
                sw_cms_element_product_slider_config_settings_speed:
                    'sw_cms_element_product_slider_config_mt_settings_speed',
                sw_cms_element_product_slider_config_settings_vertical_align:
                    'sw_cms_element_product_slider_config_mt_settings_vertical_align',
                sw_entity_single_select_base_results_list_result_label:
                    'sw_cms_element_product_slider_config_mt_entity_single_select_base_results_list_result_label',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-cms-el-config-product-slider'],
            blocks: {
                sw_cms_element_product_slider_config_content_products_select_result_item:
                    'sw_cms_element_product_slider_config_mt_content_products',
                sw_cms_element_product_slider_config_content_products_selection_label:
                    'sw_cms_element_product_slider_config_mt_content_products',
                sw_cms_element_product_slider_config_tab_content:
                    'sw_cms_element_product_slider_config_mt_tabs and add your tab to its tabs computed',
                sw_cms_element_product_slider_config_tab_options:
                    'sw_cms_element_product_slider_config_mt_tabs and add your tab to its tabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-cms-el-config-text', 'sw-cms-el-config-product-name', 'sw-cms-el-config-category-name'],
            activeSlot: ['sw_cms_el_text_config_content', 'sw_cms_el_text_config_settings'],
            blocks: {
                sw_cms_el_text_config_content: 'sw_cms_el_text_config_mt_content',
                sw_cms_el_text_config_settings: 'sw_cms_el_text_config_mt_settings',
                sw_cms_el_text_config_settings_vertical_align: 'sw_cms_el_text_config_mt_settings_vertical_align',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-cms-el-config-text', 'sw-cms-el-config-product-name', 'sw-cms-el-config-category-name'],
            blocks: {
                sw_cms_el_config_text_tab_content: 'sw_cms_el_config_text_mt_tabs and add your tab to its tabs computed',
                sw_cms_el_text_config_tab_options: 'sw_cms_el_config_text_mt_tabs and add your tab to its tabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-cms-layout-assignment-modal'],
            sharedNames: ['sw_entity_many_to_many_assignment_card_select'],
            activeSlot: [
                'sw_cms_layout_assignment_modal_category_select',
                'sw_cms_layout_assignment_modal_landing_page_select',
                'sw_cms_layout_assignment_modal_shop_pages_select',
            ],
            blocks: {
                sw_cms_layout_assignment_modal_category_select: 'sw_cms_layout_assignment_modal_mt_category_select',
                sw_cms_layout_assignment_modal_category_select_field:
                    'sw_cms_layout_assignment_modal_mt_category_select_field',
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
                sw_cms_layout_assignment_modal_shop_pages_select_field:
                    'sw_cms_layout_assignment_modal_mt_shop_pages_select_field',
                sw_cms_layout_assignment_modal_shop_pages_select_sales_channel_field:
                    'sw_cms_layout_assignment_modal_mt_shop_pages_select_sales_channel_field',
                sw_entity_many_to_many_assignment_card_select:
                    'sw_cms_layout_assignment_modal_mt_product_assignment_card_select',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-cms-layout-assignment-modal'],
            blocks: {
                sw_cms_layout_assignment_modal_tab_categories:
                    'sw_cms_layout_assignment_modal_mt_tabs and add your tab to its layoutAssignmentTabs computed',
                sw_cms_layout_assignment_modal_tab_landing_pages:
                    'sw_cms_layout_assignment_modal_mt_tabs and add your tab to its layoutAssignmentTabs computed',
                sw_cms_layout_assignment_modal_tab_shop_pages:
                    'sw_cms_layout_assignment_modal_mt_tabs and add your tab to its layoutAssignmentTabs computed',
            },
        }),
    ],
});
