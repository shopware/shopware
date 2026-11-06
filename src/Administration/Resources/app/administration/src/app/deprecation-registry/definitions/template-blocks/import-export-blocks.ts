/**
 * @sw-package framework
 */

import { removeTemplateBlocks, renameTemplateBlocks, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.import-export-6-8',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of the import/export module that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        ...removeTemplateBlocks({
            components: ['sw-import-export'],
            blocks: {
                sw_import_export_tabs_export: 'sw_import_export_mt_tabs and add your tab to its importExportTabs computed',
                sw_import_export_tabs_import: 'sw_import_export_mt_tabs and add your tab to its importExportTabs computed',
                sw_import_export_tabs_profiles: 'sw_import_export_mt_tabs and add your tab to its importExportTabs computed',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-import-export-edit-profile-modal'],
            blocks: {
                sw_import_export_edit_profile_modal_tabs_advanced: 'sw_import_export_edit_profile_modal_mt_tabs_advanced',
                sw_import_export_edit_profile_modal_tabs_advanced_identifiers:
                    'sw_import_export_edit_profile_modal_mt_tabs_advanced_identifiers',
                sw_import_export_edit_profile_modal_tabs_advanced_text:
                    'sw_import_export_edit_profile_modal_mt_tabs_advanced_text',
                sw_import_export_edit_profile_modal_tabs_general_import_settings:
                    'sw_import_export_edit_profile_modal_mt_tabs_general_import_settings',
                sw_import_export_edit_profile_modal_tabs_mappings: 'sw_import_export_edit_profile_modal_mt_tabs_mappings',
                sw_import_export_edit_profile_modal_tabs_mappings_mapping:
                    'sw_import_export_edit_profile_modal_mt_tabs_mappings_mapping',
                sw_import_export_edit_profile_modal_tabs_mappings_text:
                    'sw_import_export_edit_profile_modal_mt_tabs_mappings_text',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-import-export-edit-profile-modal'],
            blocks: {
                sw_import_export_edit_profile_modal_tabs_field_advanced:
                    'sw_import_export_edit_profile_modal_mt_tabs and add your tab to its profileTabs computed',
                sw_import_export_edit_profile_modal_tabs_field_mappings:
                    'sw_import_export_edit_profile_modal_mt_tabs and add your tab to its profileTabs computed',
                sw_import_export_edit_profile_modal_tabs_general:
                    'sw_import_export_edit_profile_modal_mt_tabs and add your tab to its profileTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-import-export-new-profile-wizard-csv-page'],
            blocks: {
                sw_import_export_new_profile_wizard_csv_page_text: 'sw_import_export_new_profile_wizard_csv_page_csv_upload',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-import-export-view-export'],
            blocks: {
                sw_import_export_view_export_activity_title:
                    'sw_import_export_view_export_activity (the title is a prop of mt-card)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-import-export-view-import'],
            blocks: {
                sw_import_export_view_import_activity_title:
                    'sw_import_export_view_import_activity (the title is a prop of mt-card)',
            },
        }),
    ],
});
