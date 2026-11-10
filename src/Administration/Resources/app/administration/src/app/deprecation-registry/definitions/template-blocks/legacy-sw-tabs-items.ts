/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { removeBlocks } from './block-steps';

/**
 * Per component: the removed blocks of the legacy `sw-tabs` items, the block that renders `mt-tabs` in
 * their place, and the computed that lists its items.
 */
const TAB_ITEMS: Record<string, { tabs: string; computed: string; blocks: string[] }> = {
    'sw-category-view': {
        tabs: 'sw_category_view_mt_tabs',
        computed: 'categoryViewTabs',
        blocks: [
            'sw_category_view_tabs_cms',
            'sw_category_view_tabs_general',
            'sw_category_view_tabs_products',
            'sw_category_view_tabs_seo',
        ],
    },
    'sw-cms-el-config-buy-box': {
        tabs: 'sw_cms_element_buy_box_config_mt_tabs',
        computed: 'tabs',
        blocks: ['sw_cms_element_buy_box_config_tab_content', 'sw_cms_element_buy_box_config_tab_option'],
    },
    'sw-cms-el-config-cross-selling': {
        tabs: 'sw_cms_element_cross_selling_config_mt_tabs',
        computed: 'tabs',
        blocks: ['sw_cms_element_cross_selling_config_tab_content', 'sw_cms_element_cross_selling_config_tab_options'],
    },
    'sw-cms-el-config-form': {
        tabs: 'sw_cms_el_config_form_mt_tabs',
        computed: 'tabs',
        blocks: ['sw_cms_el_config_form_tab_content', 'sw_cms_el_form_config_tab_options'],
    },
    'sw-cms-el-config-image-gallery': {
        tabs: 'sw_cms_element_image_gallery_config_mt_tabs',
        computed: 'tabs',
        blocks: ['sw_cms_element_image_gallery_config_tab_content', 'sw_cms_element_image_gallery_config_tab_options'],
    },
    'sw-cms-el-config-image-slider': {
        tabs: 'sw_cms_element_image_slider_config_mt_tabs',
        computed: 'tabs',
        blocks: ['sw_cms_element_image_slider_config_tab_content', 'sw_cms_element_image_slider_config_tab_options'],
    },
    'sw-cms-el-config-product-description-reviews': {
        tabs: 'sw_cms_element_product_description_reviews_config_mt_tabs',
        computed: 'tabs',
        blocks: [
            'sw_cms_element_product_description_reviews_config_tab_content',
            'sw_cms_element_product_description_reviews_config_tab_options',
        ],
    },
    'sw-cms-el-config-product-slider': {
        tabs: 'sw_cms_element_product_slider_config_mt_tabs',
        computed: 'tabs',
        blocks: ['sw_cms_element_product_slider_config_tab_content', 'sw_cms_element_product_slider_config_tab_options'],
    },
    'sw-cms-el-config-text': {
        tabs: 'sw_cms_el_config_text_mt_tabs',
        computed: 'tabs',
        blocks: ['sw_cms_el_config_text_tab_content', 'sw_cms_el_text_config_tab_options'],
    },
    'sw-cms-layout-assignment-modal': {
        tabs: 'sw_cms_layout_assignment_modal_mt_tabs',
        computed: 'layoutAssignmentTabs',
        blocks: [
            'sw_cms_layout_assignment_modal_tab_categories',
            'sw_cms_layout_assignment_modal_tab_landing_pages',
            'sw_cms_layout_assignment_modal_tab_shop_pages',
        ],
    },
    'sw-custom-field-set-renderer': {
        tabs: 'sw_custom_field_set_renderer_card_mt_tabs',
        computed: 'customFieldSetTabs',
        blocks: ['sw_custom_field_set_renderer_card_tabs'],
    },
    'sw-custom-field-translated-labels': {
        tabs: 'sw_custom_field_translated_labels_translated_mt_tabs',
        computed: 'translatedLabelTabs',
        blocks: ['sw_custom_field_translated_labels_translated_tabs'],
    },
    'sw-customer-detail': {
        tabs: 'sw_customer_detail_content_mt_tabs',
        computed: 'customerDetailTabs',
        blocks: [
            'sw_customer_detail_content_tab_addresses',
            'sw_customer_detail_content_tab_after',
            'sw_customer_detail_content_tab_general',
            'sw_customer_detail_content_tab_order',
        ],
    },
    'sw-flow-detail': {
        tabs: 'sw_flow_tabs_header_mt_tabs',
        computed: 'flowDetailTabs',
        blocks: ['sw_flow_tabs_header_builder', 'sw_flow_tabs_header_extension', 'sw_flow_tabs_header_general'],
    },
    'sw-flow-rule-modal': {
        tabs: 'sw_flow_rule_modal_mt_tabs',
        computed: 'tabs',
        blocks: ['sw_flow_rule_headers', 'sw_flow_rule_modal_tab_detail', 'sw_flow_rule_modal_tab_rule'],
    },
    'sw-import-export': {
        tabs: 'sw_import_export_mt_tabs',
        computed: 'importExportTabs',
        blocks: ['sw_import_export_tabs_export', 'sw_import_export_tabs_import', 'sw_import_export_tabs_profiles'],
    },
    'sw-import-export-edit-profile-modal': {
        tabs: 'sw_import_export_edit_profile_modal_mt_tabs',
        computed: 'profileTabs',
        blocks: [
            'sw_import_export_edit_profile_modal_tabs_field_advanced',
            'sw_import_export_edit_profile_modal_tabs_field_mappings',
            'sw_import_export_edit_profile_modal_tabs_general',
        ],
    },
    'sw-landing-page-view': {
        tabs: 'sw_landing_page_view_mt_tabs',
        computed: 'landingPageViewTabs',
        blocks: ['sw_landing_page_view_tabs_cms', 'sw_landing_page_view_tabs_general'],
    },
    'sw-media-modal-folder-settings': {
        tabs: 'sw_media_modal_folder_settings_mt_tabs',
        computed: 'tabs',
        blocks: ['sw_media_modal_folder_settings_tab_item_settings', 'sw_media_modal_folder_settings_tab_item_thumbnails'],
    },
    'sw-media-modal-v2': {
        tabs: 'sw_media_modal_v2_mt_tabs',
        computed: 'mediaModalTabs',
        blocks: ['sw_media_modal_v2_tab_item_library', 'sw_media_modal_v2_tab_item_upload', 'sw_media_modal_v2_tab_items'],
    },
    'sw-order-address-modal': {
        tabs: 'sw_order_address_modal_mt_tabs',
        computed: 'addressModalTabs',
        blocks: [
            'sw_order_address_modal_tab_edit_address',
            'sw_order_address_modal_tab_select_address',
            'sw_order_address_modal_tabs',
        ],
    },
    'sw-order-create': {
        tabs: 'sw_order_create_content_mt_tabs',
        computed: 'orderCreateTabs',
        blocks: ['sw_order_create_content_tabs_details', 'sw_order_create_content_tabs_general'],
    },
    'sw-order-create-initial-modal': {
        tabs: 'sw_order_create_modal_mt_tabs',
        computed: 'orderCreateInitialModalTabs',
        blocks: [
            'sw_order_create_modal_tabs_customer',
            'sw_order_create_modal_tabs_extension',
            'sw_order_create_modal_tabs_options',
            'sw_order_create_modal_tabs_products',
        ],
    },
    'sw-order-detail': {
        tabs: 'sw_order_detail_content_mt_tabs',
        computed: 'orderDetailTabs',
        blocks: [
            'sw_order_detail_content_tabs_details',
            'sw_order_detail_content_tabs_documents',
            'sw_order_detail_content_tabs_extension',
            'sw_order_detail_content_tabs_general',
        ],
    },
    'sw-order-new-customer-modal': {
        tabs: 'sw_order_new_customer_modal_mt_tabs',
        computed: 'newCustomerModalTabs',
        blocks: [
            'sw_order_new_customer_modal_tabs_billing',
            'sw_order_new_customer_modal_tabs_details',
            'sw_order_new_customer_modal_tabs_shipping',
        ],
    },
    'sw-product-detail': {
        tabs: 'sw_product_detail_content_mt_tabs',
        computed: 'productDetailTabs',
        blocks: [
            'sw_product_detail_content_tabs_additional',
            'sw_product_detail_content_tabs_advanced_prices',
            'sw_product_detail_content_tabs_advanced_variants',
            'sw_product_detail_content_tabs_cross_selling',
            'sw_product_detail_content_tabs_general',
            'sw_product_detail_content_tabs_layout',
            'sw_product_detail_content_tabs_reviews',
            'sw_product_detail_content_tabs_seo',
            'sw_product_detail_content_tabs_specifications',
        ],
    },
    'sw-product-modal-delivery': {
        tabs: 'sw_product_modal_delivery_sidebar_mt_tabs',
        computed: 'deliveryTabs',
        blocks: ['sw_product_modal_delivery_sidebar_tabs_items'],
    },
    'sw-product-modal-variant-generation': {
        tabs: 'sw_product_modal_variant_generation_sidebar_mt_tabs',
        computed: 'variantGenerationTabs',
        blocks: [
            'sw_product_modal_variant_generation_sidebar_tabs_item_options',
            'sw_product_modal_variant_generation_sidebar_tabs_item_prices',
            'sw_product_modal_variant_generation_sidebar_tabs_item_restrictions',
            'sw_product_modal_variant_generation_sidebar_tabs_items',
        ],
    },
    'sw-profile-index': {
        tabs: 'sw_profile_index_mt_tabs',
        computed: 'profileTabs',
        blocks: ['sw_profile_index_tabs_item_general', 'sw_profile_index_tabs_item_search_preferences'],
    },
    'sw-promotion-v2-detail': {
        tabs: 'sw_promotion_v2_detail_content_mt_tabs',
        computed: 'promotionDetailTabs',
        blocks: [
            'sw_promotion_v2_detail_content_tabs_conditions',
            'sw_promotion_v2_detail_content_tabs_discounts',
            'sw_promotion_v2_detail_content_tabs_general',
        ],
    },
    'sw-sales-channel-detail': {
        tabs: 'sw_sales_channel_detail_content_mt_tabs',
        computed: 'salesChannelDetailTabs',
        blocks: [
            'sw_sales_channel_detail_content_tab_agentic_commerce_integration',
            'sw_sales_channel_detail_content_tab_analytics',
            'sw_sales_channel_detail_content_tab_export_template',
            'sw_sales_channel_detail_content_tab_general',
            'sw_sales_channel_detail_content_tab_product_comparison',
            'sw_sales_channel_detail_content_tab_product_export_insights',
            'sw_sales_channel_detail_content_tab_products',
            'sw_sales_channel_detail_content_tab_theme',
        ],
    },
    'sw-sales-channel-products-assignment-modal': {
        tabs: 'sw_sales_channel_products_assignment_modal_mt_tabs',
        computed: 'productAssignmentTabs',
        blocks: [
            'sw_sales_channel_products_assignment_modal_tab_dynamic_product_groups',
            'sw_sales_channel_products_assignment_modal_tabs_categories',
            'sw_sales_channel_products_assignment_modal_tabs_single_products',
        ],
    },
    'sw-settings-country-detail': {
        tabs: 'sw_settings_country_mt_tabs_header',
        computed: 'countryTabs',
        blocks: [
            'sw_setting_country_tabs_address_handling',
            'sw_setting_country_tabs_extension',
            'sw_setting_country_tabs_setting',
            'sw_setting_country_tabs_state',
        ],
    },
    'sw-settings-logging-entry-info': {
        tabs: 'sw_settings_logging_entry_info_mt_tabs',
        computed: 'entryInfoTabs',
        blocks: ['sw_settings_logging_entry_info_tab_items'],
    },
    'sw-settings-rule-detail': {
        tabs: 'sw_settings_rule_detail_mt_tabs',
        computed: 'ruleDetailTabs',
        blocks: ['sw_settings_rule_detail_tab_items'],
    },
    'sw-settings-search': {
        tabs: 'sw_settings_search_mt_tabs_header',
        computed: 'settingsSearchTabs',
        blocks: ['sw_setting_search_tabs_after', 'sw_setting_search_tabs_general', 'sw_setting_search_tabs_live_search'],
    },
    'sw-settings-search-searchable-content': {
        tabs: 'sw_settings_search_searchable_content_mt_tabs',
        computed: 'searchableContentTabs',
        blocks: ['sw_settings_search_searchable_content_general_tab_title'],
    },
    'sw-settings-tag-detail-modal': {
        tabs: 'sw_settings_tag_detail_modal_mt_tabs',
        computed: 'tagDetailModalTabs',
        blocks: ['sw_settings_tag_detail_modal_tabs_assignments', 'sw_settings_tag_detail_modal_tabs_general'],
    },
};

export default templateBlockMigration({
    id: 'template-block.legacy-sw-tabs-items',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-tabs branches of these components were removed together with the blocks of their tab items. ' +
        'mt-tabs renders its tabs from an items computed, add a custom tab to it instead of overriding a block.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
    ],
    usage: [
        ...Object.entries(TAB_ITEMS).flatMap(([component, { tabs, computed, blocks }]) => {
            return removeBlocks(component, blocks, {
                to: tabs,
                message: `Add your tab to the "${computed}" computed of "${component}".`,
            });
        }),
        ...removeBlocks('sw-flow-index', ['sw_flow_tabs_header_extension'], {
            message: 'Add your tab to the "flowTabs" computed of "sw-flow-index".',
        }),
    ],
});
