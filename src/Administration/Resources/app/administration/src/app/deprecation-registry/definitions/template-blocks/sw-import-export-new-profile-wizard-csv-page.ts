/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-import-export-new-profile-wizard-csv-page',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-import-export-new-profile-wizard-csv-page that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_import_export_new_profile_wizard_csv_page_text',
            moveTo: 'sw_import_export_new_profile_wizard_csv_page_csv_upload',
            components: ['sw-import-export-new-profile-wizard-csv-page'],
        }),
    ],
});
