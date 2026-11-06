/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-import-export-edit-profile-modal',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-import-export-edit-profile-modal that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_import_export_edit_profile_modal_tabs_advanced',
            to: 'sw_import_export_edit_profile_modal_mt_tabs_advanced',
            components: ['sw-import-export-edit-profile-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_import_export_edit_profile_modal_tabs_advanced_identifiers',
            to: 'sw_import_export_edit_profile_modal_mt_tabs_advanced_identifiers',
            components: ['sw-import-export-edit-profile-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_import_export_edit_profile_modal_tabs_advanced_text',
            to: 'sw_import_export_edit_profile_modal_mt_tabs_advanced_text',
            components: ['sw-import-export-edit-profile-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_import_export_edit_profile_modal_tabs_general_import_settings',
            to: 'sw_import_export_edit_profile_modal_mt_tabs_general_import_settings',
            components: ['sw-import-export-edit-profile-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_import_export_edit_profile_modal_tabs_mappings',
            to: 'sw_import_export_edit_profile_modal_mt_tabs_mappings',
            components: ['sw-import-export-edit-profile-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_import_export_edit_profile_modal_tabs_mappings_mapping',
            to: 'sw_import_export_edit_profile_modal_mt_tabs_mappings_mapping',
            components: ['sw-import-export-edit-profile-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_import_export_edit_profile_modal_tabs_mappings_text',
            to: 'sw_import_export_edit_profile_modal_mt_tabs_mappings_text',
            components: ['sw-import-export-edit-profile-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_import_export_edit_profile_modal_tabs_field_advanced',
            moveTo: 'sw_import_export_edit_profile_modal_mt_tabs and add your tab to its profileTabs computed',
            components: ['sw-import-export-edit-profile-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_import_export_edit_profile_modal_tabs_field_mappings',
            moveTo: 'sw_import_export_edit_profile_modal_mt_tabs and add your tab to its profileTabs computed',
            components: ['sw-import-export-edit-profile-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_import_export_edit_profile_modal_tabs_general',
            moveTo: 'sw_import_export_edit_profile_modal_mt_tabs and add your tab to its profileTabs computed',
            components: ['sw-import-export-edit-profile-modal'],
        }),
    ],
});
