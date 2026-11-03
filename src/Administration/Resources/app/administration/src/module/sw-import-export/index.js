/**
 * @sw-package fundamentals@after-sales
 */
import ImportExportService from './service/importExport.service';
import ImportExportProfileMappingService from './service/importExportProfileMapping.service';
import ImportExportProfileUpdateByService from './service/importExportUpdateByMapping.service';
import './acl';
import './component/sw-import-export-activity/register';
import './component/profile-wizard/sw-import-export-new-profile-wizard/register';
import './component/profile-wizard/sw-import-export-new-profile-wizard-csv-page/register';
import './component/profile-wizard/sw-import-export-new-profile-wizard-general-page/register';
import './component/profile-wizard/sw-import-export-new-profile-wizard-mapping-page/register';
import './component/sw-import-export-activity-log-info-modal/register';
import './component/sw-import-export-activity-result-modal/register';
import './component/sw-import-export-edit-profile-field-indicators/register';
import './component/sw-import-export-edit-profile-general/register';
import './component/sw-import-export-edit-profile-import-settings/register';
import './component/sw-import-export-edit-profile-modal/register';
import './component/sw-import-export-edit-profile-modal-identifiers/register';
import './component/sw-import-export-edit-profile-modal-mapping/register';
import './component/sw-import-export-entity-path-select/register';
import './component/sw-import-export-exporter/register';
import './component/sw-import-export-importer/register';
import './component/sw-import-export-progress/register';
import './page/sw-import-export/register';
import './view/sw-import-export-view-export/register';
import './view/sw-import-export-view-import/register';
import './view/sw-import-export-view-profiles/register';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('importExport', () => {
    return new ImportExportService(Shopware.Application.getContainer('init').httpClient, Shopware.Service('loginService'));
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('importExportProfileMapping', () => {
    return new ImportExportProfileMappingService(Shopware.EntityDefinition);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('importExportUpdateByMapping', () => {
    return new ImportExportProfileUpdateByService(Shopware.EntityDefinition);
});

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-import-export', {
    type: 'core',
    name: 'ImportExport',
    title: 'sw-import-export.general.mainMenuItemGeneral',
    description: 'sw-import-export.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-database',
    entity: 'import_export_profile',
    routePrefixPath: 'sw/import-export',

    routes: {
        index: {
            component: 'sw-import-export',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.import_export',
            },
            redirect: {
                name: 'sw.import.export.index.import',
            },

            children: {
                import: {
                    component: 'sw-import-export-view-import',
                    path: 'import',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'system.import_export',
                    },
                },
                export: {
                    component: 'sw-import-export-view-export',
                    path: 'export',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'system.import_export',
                    },
                },
                profiles: {
                    component: 'sw-import-export-view-profiles',
                    path: 'profiles',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'system.import_export',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'automation',
        to: 'sw.import.export.index',
        icon: 'regular-database',
        privilege: 'system.import_export',
    },
});
