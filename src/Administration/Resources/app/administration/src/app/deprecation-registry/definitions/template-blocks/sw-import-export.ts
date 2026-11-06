/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-import-export',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-import-export that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_import_export_tabs_export',
            moveTo: 'sw_import_export_mt_tabs and add your tab to its importExportTabs computed',
            components: ['sw-import-export'],
        }),
        removeTemplateBlock({
            from: 'sw_import_export_tabs_import',
            moveTo: 'sw_import_export_mt_tabs and add your tab to its importExportTabs computed',
            components: ['sw-import-export'],
        }),
        removeTemplateBlock({
            from: 'sw_import_export_tabs_profiles',
            moveTo: 'sw_import_export_mt_tabs and add your tab to its importExportTabs computed',
            components: ['sw-import-export'],
        }),
    ],
});
