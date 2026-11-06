/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-cms-el-config-product-slider',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-cms-el-config-product-slider that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#renamed-cms-element-config-blocks'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
    ],
    usage: [
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content',
            to: 'sw_cms_element_product_slider_config_mt_content',
            components: ['sw-cms-el-config-product-slider'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_product_assignment_type',
            to: 'sw_cms_element_product_slider_config_mt_content_product_assignment_type',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_product_stream_limit',
            to: 'sw_cms_element_product_slider_config_mt_content_product_stream_limit',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_product_stream_performance_hint',
            to: 'sw_cms_element_product_slider_config_mt_content_product_stream_performance_hint',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_product_stream_preview_link',
            to: 'sw_cms_element_product_slider_config_mt_content_product_stream_preview_link',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_product_stream_select',
            to: 'sw_cms_element_product_slider_config_mt_content_product_stream_select',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_product_stream_sorting',
            to: 'sw_cms_element_product_slider_config_mt_content_product_stream_sorting',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_products',
            to: 'sw_cms_element_product_slider_config_mt_content_products',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_products_select_result_item_inner',
            to: 'sw_cms_element_product_slider_config_mt_entity_single_select_base_results_list_result_label',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_title',
            to: 'sw_cms_element_product_slider_config_mt_content_title',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_settings',
            to: 'sw_cms_element_product_slider_config_mt_settings',
            components: ['sw-cms-el-config-product-slider'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_settings_autoplay_timeout',
            to: 'sw_cms_element_product_slider_config_mt_settings_autoplay_timeout',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_settings_border',
            to: 'sw_cms_element_product_slider_config_mt_settings_border',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_settings_box_layout',
            to: 'sw_cms_element_product_slider_config_mt_settings_box_layout',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_settings_display_mode',
            to: 'sw_cms_element_product_slider_config_mt_settings_display_mode',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_settings_min_width',
            to: 'sw_cms_element_product_slider_config_mt_settings_min_width',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_settings_navigation_arrows',
            to: 'sw_cms_element_product_slider_config_mt_settings_navigation_arrows',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_settings_rotate',
            to: 'sw_cms_element_product_slider_config_mt_settings_rotate',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_settings_speed',
            to: 'sw_cms_element_product_slider_config_mt_settings_speed',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_slider_config_settings_vertical_align',
            to: 'sw_cms_element_product_slider_config_mt_settings_vertical_align',
            components: ['sw-cms-el-config-product-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_entity_single_select_base_results_list_result_label',
            to: 'sw_cms_element_product_slider_config_mt_entity_single_select_base_results_list_result_label',
            components: ['sw-cms-el-config-product-slider'],
            onlyInComponents: true,
        }),
        removeTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_products_select_result_item',
            moveTo: 'sw_cms_element_product_slider_config_mt_content_products',
            components: ['sw-cms-el-config-product-slider'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_element_product_slider_config_content_products_selection_label',
            moveTo: 'sw_cms_element_product_slider_config_mt_content_products',
            components: ['sw-cms-el-config-product-slider'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_element_product_slider_config_tab_content',
            moveTo: 'sw_cms_element_product_slider_config_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-product-slider'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_element_product_slider_config_tab_options',
            moveTo: 'sw_cms_element_product_slider_config_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-product-slider'],
        }),
    ],
});
