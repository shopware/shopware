import { reference, removeTemplateBlock, templateBlockMigration } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.import-export-profile-label',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Several import/export profile Twig blocks are deprecated because the surrounding Administration markup was removed or renamed.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#administration' }),
    ],
    usage: [
        removeTemplateBlock({ from: 'sw_import_export_edit_profile_general_container_name', fix: 'manual' }),
        removeTemplateBlock({
            from: 'sw_import_export_view_profile_profiles_listing_column_label',
            fix: 'manual',
        }),
        removeTemplateBlock({ from: 'sw_import_export_language_switch', fix: 'manual' }),
    ],
});
