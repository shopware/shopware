/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-cms-el-config-buy-box',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-cms-el-config-buy-box that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#renamed-cms-element-config-blocks'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
    ],
    usage: [
        renameTemplateBlock({
            from: 'sw_cms_element_buy_box_config_content_warning',
            to: 'sw_cms_element_buy_box_config_mt_content_warning',
            components: ['sw-cms-el-config-buy-box'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_buy_box_config_options',
            to: 'sw_cms_element_buy_box_config_mt_options',
            components: ['sw-cms-el-config-buy-box'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_buy_box_config_product_select',
            to: 'sw_cms_element_buy_box_config_mt_product_select',
            components: ['sw-cms-el-config-buy-box'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_buy_box_config_product_select_result_item',
            to: 'sw_cms_element_buy_box_config_mt_product_select_result_item',
            components: ['sw-cms-el-config-buy-box'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_buy_box_config_product_select_result_item_inner',
            to: 'sw_cms_element_buy_box_config_mt_product_select_result_label',
            components: ['sw-cms-el-config-buy-box'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_buy_box_config_product_selection_label',
            to: 'sw_cms_element_buy_box_config_mt_product_variant_label',
            components: ['sw-cms-el-config-buy-box'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_buy_box_config_product_variant_label',
            to: 'sw_cms_element_buy_box_config_mt_product_variant_label',
            components: ['sw-cms-el-config-buy-box'],
        }),
        renameTemplateBlock({
            from: 'sw_entity_single_select_base_results_list_result_label',
            to: 'sw_cms_element_buy_box_config_mt_product_select_result_label',
            components: ['sw-cms-el-config-buy-box'],
            onlyInComponents: true,
        }),
        removeTemplateBlock({
            from: 'sw_cms_element_buy_box_config_tab_content',
            moveTo: 'sw_cms_element_buy_box_config_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-buy-box'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_element_buy_box_config_tab_option',
            moveTo: 'sw_cms_element_buy_box_config_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-buy-box'],
        }),
    ],
});
