/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-cms-el-config-cross-selling',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-cms-el-config-cross-selling that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#renamed-cms-element-config-blocks'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
    ],
    usage: [
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_content',
            to: 'sw_cms_element_cross_selling_config_mt_content',
            components: ['sw-cms-el-config-cross-selling'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_content_products',
            to: 'sw_cms_element_cross_selling_config_mt_content_products',
            components: ['sw-cms-el-config-cross-selling'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_content_products_select_result_item',
            to: 'sw_cms_element_cross_selling_config_mt_product_select_result_item',
            components: ['sw-cms-el-config-cross-selling'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_content_products_select_result_item_inner',
            to: 'sw_cms_element_cross_selling_config_mt_product_select_result_label',
            components: ['sw-cms-el-config-cross-selling'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_content_products_selection_label',
            to: 'sw_cms_element_cross_selling_config_mt_product_variant_label',
            components: ['sw-cms-el-config-cross-selling'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_content_warning_text',
            to: 'sw_cms_element_cross_selling_config_mt_content_warning_text',
            components: ['sw-cms-el-config-cross-selling'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_options',
            to: 'sw_cms_element_cross_selling_config_mt_options',
            components: ['sw-cms-el-config-cross-selling'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_options_box_layout',
            to: 'sw_cms_element_cross_selling_config_mt_options_box_layout',
            components: ['sw-cms-el-config-cross-selling'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_options_display_mode',
            to: 'sw_cms_element_cross_selling_config_mt_options_display_mode',
            components: ['sw-cms-el-config-cross-selling'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_options_min_width',
            to: 'sw_cms_element_cross_selling_config_mt_options_min_width',
            components: ['sw-cms-el-config-cross-selling'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_options_speed',
            to: 'sw_cms_element_cross_selling_config_mt_options_speed',
            components: ['sw-cms-el-config-cross-selling'],
        }),
        renameTemplateBlock({
            from: 'sw_entity_single_select_base_results_list_result_label',
            to: 'sw_cms_element_cross_selling_config_mt_product_select_result_label',
            components: ['sw-cms-el-config-cross-selling'],
            onlyInComponents: true,
        }),
        renameTemplateBlock({
            from: 'sw_entity_single_select_variant_result_item',
            to: 'sw_cms_element_cross_selling_config_mt_product_select_result_item',
            components: ['sw-cms-el-config-cross-selling'],
            onlyInComponents: true,
        }),
        renameTemplateBlock({
            from: 'sw_entity_single_select_variant_selected_item',
            to: 'sw_cms_element_cross_selling_config_mt_product_variant_label',
            components: ['sw-cms-el-config-cross-selling'],
            onlyInComponents: true,
        }),
        removeTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_tab_content',
            moveTo: 'sw_cms_element_cross_selling_config_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-cross-selling'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_element_cross_selling_config_tab_options',
            moveTo: 'sw_cms_element_cross_selling_config_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-cross-selling'],
        }),
    ],
});
