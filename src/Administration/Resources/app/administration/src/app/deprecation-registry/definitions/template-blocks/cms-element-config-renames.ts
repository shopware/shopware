/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { renameBlocks } from './block-steps';

/**
 * Old block → new block per component. The `sw_entity_*` names are the blocks of the select fields
 * that the element configs overrode; the other old names belonged to the legacy `sw-tabs` branch.
 */
const RENAMES: Record<string, Record<string, string>> = {
    'sw-cms-el-config-buy-box': {
        sw_cms_element_buy_box_config_product_select_result_item_inner:
            'sw_cms_element_buy_box_config_mt_product_select_result_label',
        sw_cms_element_buy_box_config_product_selection_label: 'sw_cms_element_buy_box_config_mt_product_variant_label',
        sw_cms_element_buy_box_config_product_variant_label: 'sw_cms_element_buy_box_config_mt_product_variant_label',
        sw_entity_single_select_base_results_list_result_label:
            'sw_cms_element_buy_box_config_mt_product_select_result_label',
    },
    'sw-cms-el-config-cross-selling': {
        sw_cms_element_cross_selling_config_content_products_select_result_item:
            'sw_cms_element_cross_selling_config_mt_product_select_result_item',
        sw_cms_element_cross_selling_config_content_products_select_result_item_inner:
            'sw_cms_element_cross_selling_config_mt_product_select_result_label',
        sw_cms_element_cross_selling_config_content_products_selection_label:
            'sw_cms_element_cross_selling_config_mt_product_variant_label',
        sw_entity_single_select_base_results_list_result_label:
            'sw_cms_element_cross_selling_config_mt_product_select_result_label',
        sw_entity_single_select_variant_result_item: 'sw_cms_element_cross_selling_config_mt_product_select_result_item',
        sw_entity_single_select_variant_selected_item: 'sw_cms_element_cross_selling_config_mt_product_variant_label',
    },
    'sw-cms-el-config-product-box': {
        sw_entity_single_select_base_results_list_result_label:
            'sw_cms_element_product_box_config_product_select_result_item_inner',
    },
    'sw-cms-el-config-product-description-reviews': {
        sw_cms_element_product_description_reviews_config_product_select_result_item:
            'sw_cms_element_product_description_reviews_config_mt_product_select_result_item',
        sw_cms_element_product_description_reviews_config_product_select_result_item_inner:
            'sw_cms_element_product_description_reviews_config_mt_product_select_result_label',
        sw_cms_element_product_description_reviews_config_product_selection_label:
            'sw_cms_element_product_description_reviews_config_mt_product_variant_label',
        sw_entity_single_select_base_results_list_result_label:
            'sw_cms_element_product_description_reviews_config_mt_product_select_result_label',
        sw_entity_single_select_variant_result_item:
            'sw_cms_element_product_description_reviews_config_mt_product_select_result_item',
        sw_entity_single_select_variant_selected_item:
            'sw_cms_element_product_description_reviews_config_mt_product_variant_label',
    },
    'sw-cms-el-config-product-slider': {
        sw_cms_element_product_slider_config_content_products_select_result_item_inner:
            'sw_cms_element_product_slider_config_mt_entity_single_select_base_results_list_result_label',
        sw_entity_single_select_base_results_list_result_label:
            'sw_cms_element_product_slider_config_mt_entity_single_select_base_results_list_result_label',
    },
    'sw-product-cross-selling-assignment': {
        sw_entity_single_select_base_results_list_result_label:
            'sw_product_cross_selling_assignment_select_result_item_inner',
    },
};

export default templateBlockMigration({
    id: 'template-block.cms-element-config-renames',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The product select blocks of the CMS element configs and of sw-product-cross-selling-assignment were renamed, ' +
        'because they reused the block names of the select field components.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#renamed-cms-element-config-blocks'),
    ],
    usage: renameBlocks(RENAMES),
});
