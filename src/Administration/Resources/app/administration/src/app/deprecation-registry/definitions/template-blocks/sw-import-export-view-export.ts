/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-import-export-view-export',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-import-export-view-export that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_import_export_view_export_activity_title',
            moveTo: 'sw_import_export_view_export_activity (the title is a prop of mt-card)',
            components: ['sw-import-export-view-export'],
        }),
    ],
});
