/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-cms-el-config-form',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-cms-el-config-form that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_cms_el_form_config_content',
            to: 'sw_cms_el_form_config_mt_content',
            components: ['sw-cms-el-config-form'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_el_form_config_content_form_confirmation_text',
            to: 'sw_cms_el_form_config_mt_content_form_confirmation_text',
            components: ['sw-cms-el-config-form'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_el_form_config_content_form_title',
            to: 'sw_cms_el_form_config_mt_content_form_title',
            components: ['sw-cms-el-config-form'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_el_form_config_content_form_type',
            to: 'sw_cms_el_form_config_mt_content_form_type',
            components: ['sw-cms-el-config-form'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_el_form_config_options',
            to: 'sw_cms_el_form_config_mt_options',
            components: ['sw-cms-el-config-form'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        removeTemplateBlock({
            from: 'sw_cms_el_config_form_tab_content',
            moveTo: 'sw_cms_el_config_form_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-form'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_el_form_config_tab_options',
            moveTo: 'sw_cms_el_config_form_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-form'],
        }),
    ],
});
