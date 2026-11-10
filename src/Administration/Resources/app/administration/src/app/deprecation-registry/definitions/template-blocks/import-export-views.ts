/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { moveBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.import-export-views',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'The activity cards of the import and export views take their title as a prop of mt-card, ' +
        'and the CSV page of the new profile wizard lost its text block.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-import-export-view-import'),
        upgradeGuide('UPGRADE-6.8.md#sw-import-export-view-export'),
        upgradeGuide('UPGRADE-6.8.md#sw-import-export-new-profile-wizard-csv-page'),
    ],
    usage: [
        ...moveBlocks(
            {
                'sw-import-export-view-import': {
                    sw_import_export_view_import_activity: ['sw_import_export_view_import_activity_title'],
                },
                'sw-import-export-view-export': {
                    sw_import_export_view_export_activity: ['sw_import_export_view_export_activity_title'],
                },
            },
            'The title is a prop of mt-card.',
        ),
        ...moveBlocks({
            'sw-import-export-new-profile-wizard-csv-page': {
                sw_import_export_new_profile_wizard_csv_page_csv_upload: [
                    'sw_import_export_new_profile_wizard_csv_page_text',
                ],
            },
        }),
    ],
});
