/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-cms-el-config-text',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-cms-el-config-text that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_cms_el_text_config_content',
            to: 'sw_cms_el_text_config_mt_content',
            components: ['sw-cms-el-config-text', 'sw-cms-el-config-product-name', 'sw-cms-el-config-category-name'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_el_text_config_settings',
            to: 'sw_cms_el_text_config_mt_settings',
            components: ['sw-cms-el-config-text', 'sw-cms-el-config-product-name', 'sw-cms-el-config-category-name'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_el_text_config_settings_vertical_align',
            to: 'sw_cms_el_text_config_mt_settings_vertical_align',
            components: ['sw-cms-el-config-text', 'sw-cms-el-config-product-name', 'sw-cms-el-config-category-name'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_el_config_text_tab_content',
            moveTo: 'sw_cms_el_config_text_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-text', 'sw-cms-el-config-product-name', 'sw-cms-el-config-category-name'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_el_text_config_tab_options',
            moveTo: 'sw_cms_el_config_text_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-text', 'sw-cms-el-config-product-name', 'sw-cms-el-config-category-name'],
        }),
    ],
});
