/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-theme-manager-detail',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-theme-manager-detail that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_content_areas',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_content_fields',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_content_info',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_content_info_content',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_content_info_context_button',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_content_info_image',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_content_inheritance',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_content_inheritance_icon',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_content_inheritance_text',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_content_sections',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_context_button_option_create',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_context_button_option_delete',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_context_button_option_rename',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_context_button_option_reset',
            moveTo: 'sw_theme_manager_detail_content (no blocks in the mt-tabs branch)',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_sidebar',
            moveTo: 'sw_theme_manager_detail_media_modal',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_sidebar_media',
            moveTo: 'sw_theme_manager_detail_media_modal',
            components: ['sw-theme-manager-detail'],
        }),
        removeTemplateBlock({
            from: 'sw_theme_manager_detail_sidebar_media_items',
            moveTo: 'sw_theme_manager_detail_media_modal',
            components: ['sw-theme-manager-detail'],
        }),
    ],
});
