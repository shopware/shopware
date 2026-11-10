/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.import-export-profile-label',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Several import/export profile Twig blocks are deprecated because the surrounding Administration markup was removed or renamed.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#administration'),
        upgradeGuide('UPGRADE-6.8.md#removed-translation-of-importexport-profile-label'),
    ],
    usage: [
        removeTemplateBlock({
            from: 'sw_import_export_edit_profile_general_container_name',
            to: 'sw_import_export_edit_profile_general_container_technical_name',
        }),
        removeTemplateBlock({
            from: 'sw_import_export_view_profile_profiles_listing_column_label',
            to: 'sw_import_export_view_profile_profiles_listing',
        }),
        removeTemplateBlock({ from: 'sw_import_export_language_switch' }),
    ],
});
