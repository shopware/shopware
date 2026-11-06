/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-language-list',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-language-list that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_language_list_content_list_column_id',
            moveTo: 'sw_settings_language_list_content_list_columns',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_content_list_delete_confirm_action',
            moveTo: 'sw_settings_language_list_delete_modal',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_grid_sidebar_filter',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_grid_sidebar_filter_content',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_grid_sidebar_filter_content_inherited_languages',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_grid_sidebar_filter_content_root_languages',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_grid_sidebar_filter_header',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_grid_sidebar_filter_header_icon',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_grid_sidebar_filter_header_icon_expanded',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_grid_sidebar_filter_header_icon_not_expanded',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_grid_sidebar_filter_header_title',
            components: ['sw-settings-language-list'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_language_list_smart_bar_header_amount',
            moveTo: 'sw_settings_language_list_smart_bar_header_title',
            components: ['sw-settings-language-list'],
        }),
    ],
});
