/**
 * @sw-package framework
 */

import { removeTemplateBlocks, renameTemplateBlocks, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.media-6-8',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of the media module that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        ...renameTemplateBlocks({
            components: ['sw-media-modal-folder-settings'],
            activeSlot: [
                'sw_media_modal_folder_settings_tab_content_settings',
                'sw_media_modal_folder_settings_tab_content_thumbnails',
            ],
            blocks: {
                sw_media_modal_folder_settings_default_folder: 'sw_media_modal_folder_settings_mt_default_folder',
                sw_media_modal_folder_settings_generate_thumbnails_field:
                    'sw_media_modal_folder_settings_mt_generate_thumbnails_field',
                sw_media_modal_folder_settings_inherit_settings_field:
                    'sw_media_modal_folder_settings_mt_inherit_settings_field',
                sw_media_modal_folder_settings_keep_proportions_field:
                    'sw_media_modal_folder_settings_mt_keep_proportions_field',
                sw_media_modal_folder_settings_name_field: 'sw_media_modal_folder_settings_mt_name_field',
                sw_media_modal_folder_settings_tab_content_settings:
                    'sw_media_modal_folder_settings_mt_tab_content_settings',
                sw_media_modal_folder_settings_tab_content_thumbnails:
                    'sw_media_modal_folder_settings_mt_tab_content_thumbnails',
                sw_media_modal_folder_settings_tab_content_thumbnails_left_container:
                    'sw_media_modal_folder_settings_mt_tab_content_thumbnails_left_container',
                sw_media_modal_folder_settings_tab_content_thumbnails_right_container:
                    'sw_media_modal_folder_settings_mt_tab_content_thumbnails_right_container',
                sw_media_modal_folder_settings_thumbnail_list: 'sw_media_modal_folder_settings_mt_thumbnail_list',
                sw_media_modal_folder_settings_thumbnail_list_caption:
                    'sw_media_modal_folder_settings_mt_thumbnail_list_caption',
                sw_media_modal_folder_settings_thumbnail_list_container:
                    'sw_media_modal_folder_settings_mt_thumbnail_list_container',
                sw_media_modal_folder_settings_thumbnail_size: 'sw_media_modal_folder_settings_mt_thumbnail_size',
                sw_media_modal_folder_settings_thumbnail_size_delete_button:
                    'sw_media_modal_folder_settings_mt_thumbnail_size_delete_button',
                sw_media_modal_folder_settings_thumbnail_size_switch:
                    'sw_media_modal_folder_settings_mt_thumbnail_size_switch',
                sw_media_modal_folder_settings_thumbnails_quality_field:
                    'sw_media_modal_folder_settings_mt_thumbnails_quality_field',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-media-modal-folder-settings'],
            blocks: {
                sw_media_modal_folder_settings_tab_item_settings:
                    'sw_media_modal_folder_settings_mt_tabs and add your tab to its tabs computed',
                sw_media_modal_folder_settings_tab_item_thumbnails:
                    'sw_media_modal_folder_settings_mt_tabs and add your tab to its tabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-media-modal-v2'],
            activeSlot: ['sw_media_modal_v2_tab_content_library', 'sw_media_modal_v2_tab_content_upload'],
            blocks: {
                sw_media_modal_v2_folder_breadcrumbs: 'sw_media_modal_v2_mt_folder_breadcrumbs',
                sw_media_modal_v2_media_library: 'sw_media_modal_v2_mt_media_library',
                sw_media_modal_v2_navigation_and_search: 'sw_media_modal_v2_mt_navigation_and_search',
                sw_media_modal_v2_search_field: 'sw_media_modal_v2_mt_search_field',
                sw_media_modal_v2_tab_content_library: 'sw_media_modal_v2_mt_tab_content_library',
                sw_media_modal_v2_tab_content_upload: 'sw_media_modal_v2_mt_tab_content_upload',
                sw_media_modal_v2_upload_component: 'sw_media_modal_v2_mt_upload_component',
                sw_media_modal_v2_uploaded_items: 'sw_media_modal_v2_mt_uploaded_items',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-media-modal-v2'],
            blocks: {
                sw_media_modal_v2_tab_content: 'sw_media_modal_v2_mt_tab_content (the markup changed)',
                sw_media_modal_v2_tab_item_library:
                    'sw_media_modal_v2_mt_tabs and add your tab to its mediaModalTabs computed',
                sw_media_modal_v2_tab_item_upload:
                    'sw_media_modal_v2_mt_tabs and add your tab to its mediaModalTabs computed',
                sw_media_modal_v2_tab_items: 'sw_media_modal_v2_mt_tabs and add your tab to its mediaModalTabs computed',
            },
        }),
    ],
});
