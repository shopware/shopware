/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-media-modal-v2',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-media-modal-v2 that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        renameTemplateBlock({
            from: 'sw_media_modal_v2_folder_breadcrumbs',
            to: 'sw_media_modal_v2_mt_folder_breadcrumbs',
            components: ['sw-media-modal-v2'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_v2_media_library',
            to: 'sw_media_modal_v2_mt_media_library',
            components: ['sw-media-modal-v2'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_v2_navigation_and_search',
            to: 'sw_media_modal_v2_mt_navigation_and_search',
            components: ['sw-media-modal-v2'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_v2_search_field',
            to: 'sw_media_modal_v2_mt_search_field',
            components: ['sw-media-modal-v2'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_v2_tab_content_library',
            to: 'sw_media_modal_v2_mt_tab_content_library',
            components: ['sw-media-modal-v2'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_v2_tab_content_upload',
            to: 'sw_media_modal_v2_mt_tab_content_upload',
            components: ['sw-media-modal-v2'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_v2_upload_component',
            to: 'sw_media_modal_v2_mt_upload_component',
            components: ['sw-media-modal-v2'],
        }),
        renameTemplateBlock({
            from: 'sw_media_modal_v2_uploaded_items',
            to: 'sw_media_modal_v2_mt_uploaded_items',
            components: ['sw-media-modal-v2'],
        }),
        removeTemplateBlock({
            from: 'sw_media_modal_v2_tab_content',
            moveTo: 'sw_media_modal_v2_mt_tab_content (the markup changed)',
            components: ['sw-media-modal-v2'],
        }),
        removeTemplateBlock({
            from: 'sw_media_modal_v2_tab_item_library',
            moveTo: 'sw_media_modal_v2_mt_tabs and add your tab to its mediaModalTabs computed',
            components: ['sw-media-modal-v2'],
        }),
        removeTemplateBlock({
            from: 'sw_media_modal_v2_tab_item_upload',
            moveTo: 'sw_media_modal_v2_mt_tabs and add your tab to its mediaModalTabs computed',
            components: ['sw-media-modal-v2'],
        }),
        removeTemplateBlock({
            from: 'sw_media_modal_v2_tab_items',
            moveTo: 'sw_media_modal_v2_mt_tabs and add your tab to its mediaModalTabs computed',
            components: ['sw-media-modal-v2'],
        }),
    ],
});
