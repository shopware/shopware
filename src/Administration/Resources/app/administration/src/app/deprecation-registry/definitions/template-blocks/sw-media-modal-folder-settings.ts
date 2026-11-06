/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-media-modal-folder-settings',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-media-modal-folder-settings that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_default_folder',
            to: 'sw_media_modal_folder_settings_mt_default_folder',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_generate_thumbnails_field',
            to: 'sw_media_modal_folder_settings_mt_generate_thumbnails_field',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_inherit_settings_field',
            to: 'sw_media_modal_folder_settings_mt_inherit_settings_field',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_keep_proportions_field',
            to: 'sw_media_modal_folder_settings_mt_keep_proportions_field',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_name_field',
            to: 'sw_media_modal_folder_settings_mt_name_field',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_tab_content_settings',
            to: 'sw_media_modal_folder_settings_mt_tab_content_settings',
            components: ['sw-media-modal-folder-settings'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_tab_content_thumbnails',
            to: 'sw_media_modal_folder_settings_mt_tab_content_thumbnails',
            components: ['sw-media-modal-folder-settings'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_tab_content_thumbnails_left_container',
            to: 'sw_media_modal_folder_settings_mt_tab_content_thumbnails_left_container',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_tab_content_thumbnails_right_container',
            to: 'sw_media_modal_folder_settings_mt_tab_content_thumbnails_right_container',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_thumbnail_list',
            to: 'sw_media_modal_folder_settings_mt_thumbnail_list',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_thumbnail_list_caption',
            to: 'sw_media_modal_folder_settings_mt_thumbnail_list_caption',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_thumbnail_list_container',
            to: 'sw_media_modal_folder_settings_mt_thumbnail_list_container',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_thumbnail_size',
            to: 'sw_media_modal_folder_settings_mt_thumbnail_size',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_thumbnail_size_delete_button',
            to: 'sw_media_modal_folder_settings_mt_thumbnail_size_delete_button',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_thumbnail_size_switch',
            to: 'sw_media_modal_folder_settings_mt_thumbnail_size_switch',
            components: ['sw-media-modal-folder-settings'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_folder_settings_thumbnails_quality_field',
            to: 'sw_media_modal_folder_settings_mt_thumbnails_quality_field',
            components: ['sw-media-modal-folder-settings'],
        }),
        removeTemplateBlock({
            from: 'sw_media_modal_folder_settings_tab_item_settings',
            moveTo: 'sw_media_modal_folder_settings_mt_tabs and add your tab to its tabs computed',
            components: ['sw-media-modal-folder-settings'],
        }),
        removeTemplateBlock({
            from: 'sw_media_modal_folder_settings_tab_item_thumbnails',
            moveTo: 'sw_media_modal_folder_settings_mt_tabs and add your tab to its tabs computed',
            components: ['sw-media-modal-folder-settings'],
        }),
    ],
});
