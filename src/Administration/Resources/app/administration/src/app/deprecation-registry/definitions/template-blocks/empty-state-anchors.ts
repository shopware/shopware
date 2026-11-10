/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { moveBlocks } from './block-steps';

/** Removed anchor blocks per component, by the surrounding `mt-empty-state` block that takes their content as props. */
const ANCHORS: Record<string, Record<string, string[]>> = {
    'sw-flow-list': {
        sw_flow_list_empty_state: ['sw_flow_list_empty_state_icon'],
    },
    'sw-mail-header-footer-list': {
        sw_mail_header_footer_list_grid_empty_state: ['sw_mail_header_footer_list_grid_empty_state_icon'],
    },
    'sw-mail-template-list': {
        sw_mail_template_list_grid_empty_state: ['sw_mail_template_list_grid_empty_state_icon'],
    },
    'sw-order-create-address-modal': {
        sw_order_create_address_modal_empty_state: ['sw_order_create_address_modal_empty_state_content'],
    },
    'sw-order-customer-grid': {
        sw_order_customer_grid_empty_state: ['sw_order_customer_grid_empty_state_icon'],
    },
    'sw-product-detail-context-prices': {
        sw_product_detail_prices_price_empty_state: [
            'sw_product_detail_prices_empty_state_image',
            'sw_product_detail_prices_price_empty_state_text',
            'sw_product_detail_prices_price_empty_state_text_child',
            'sw_product_detail_prices_price_empty_state_text_empty',
            'sw_product_detail_prices_price_empty_state_text_inherited',
            'sw_product_detail_prices_price_empty_state_text_link',
            'sw_product_detail_prices_price_empty_state_text_not_inherited',
        ],
    },
    'sw-product-detail-cross-selling': {
        sw_product_detail_cross_selling_empty_state: [
            'sw_product_detail_cross_selling_empty_state_actions',
            'sw_product_detail_cross_selling_empty_state_content',
            'sw_product_detail_cross_selling_empty_state_content_child',
            'sw_product_detail_cross_selling_empty_state_content_child_inherited',
            'sw_product_detail_cross_selling_empty_state_content_child_inherited_link',
            'sw_product_detail_cross_selling_empty_state_content_child_not_inherited',
            'sw_product_detail_cross_selling_empty_state_content_empty',
            'sw_product_detail_cross_selling_empty_state_icon',
        ],
    },
    'sw-promotion-v2-individual-codes-behavior': {
        sw_promotion_v2_individual_codes_behavior_empty_state: [
            'sw_promotion_v2_individual_codes_behavior_empty_state_icon',
        ],
    },
    'sw-sales-channel-products-assignment-dynamic-product-groups': {
        sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty: [
            'sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty_icon',
        ],
    },
    'sw-settings-listing': {
        sw_settings_listing_content_card_view_options_card_empty_state: [
            'sw_settings_listing_content_card_view_options_card_empty_state_icon',
        ],
    },
    'sw-settings-listing-option-criteria-grid': {
        sw_settings_listing_option_criteria_card_empty_state: ['sw_settings_listing_option_criteria_card_empty_state_icon'],
    },
    'sw-settings-product-feature-sets-values-card': {
        sw_product_feature_set_card_empty_state: [
            'sw_product_feature_set_card_empty_state_image',
            'sw_product_feature_set_card_empty_state_label',
        ],
    },
    'sw-tax-rule-card': {
        sw_tax_rule_card_empty_state: [
            'sw_tax_rule_card_empty_state_image',
            'sw_tax_rule_card_empty_state_label',
        ],
    },
};

/** Removed image and action blocks per component, by the empty state block around them. */
const IMAGES_AND_ACTIONS: Record<string, Record<string, string[]>> = {
    'sw-product-properties': {
        sw_product_properties_empty_state: ['sw_product_properties_empty_state_image'],
        sw_product_properties_filled_state_body_empty_state: ['sw_product_properties_filled_state_body_empty_state_image'],
    },
    'sw-promotion-v2-list': {
        sw_promotion_v2_list_empty_state: ['sw_promotion_v2_list_empty_state_actions'],
    },
    'sw-property-search': {
        sw_property_search_empty: ['sw_property_search_empty_image'],
    },
    'sw-sales-channel-detail-products': {
        sw_sales_channel_detail_products_card_section_primary_empty_state: [
            'sw_sales_channel_detail_products_card_section_primary_image',
        ],
        sw_sales_channel_detail_products_empty_state: ['sw_sales_channel_detail_products_empty_state_image'],
    },
};

export default templateBlockMigration({
    id: 'template-block.empty-state-anchors',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The empty states of these components moved to mt-empty-state, which takes its icon and texts as props. ' +
        'The empty anchor, image and action blocks inside them were removed.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-empty-state-twig-block-anchors'),
    ],
    usage: [
        ...moveBlocks(ANCHORS, 'Pass the icon and the texts as props of mt-empty-state instead.'),
        ...moveBlocks(IMAGES_AND_ACTIONS),
    ],
});
