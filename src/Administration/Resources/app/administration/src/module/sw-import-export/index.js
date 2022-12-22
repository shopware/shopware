/**
 * @package system-settings
 */
import ImportExportService from './service/importExport.service';
import ImportExportProfileMappingService from './service/importExportProfileMapping.service';
import ImportExportProfileUpdateByService from './service/importExportUpdateByMapping.service';
import './acl';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('importExport', () => {
    return new ImportExportService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});


// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('importExportProfileMapping', () => {
    return new ImportExportProfileMappingService(Shopware.EntityDefinition);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('importExportUpdateByMapping', () => {
    return new ImportExportProfileUpdateByService(Shopware.EntityDefinition);
});

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-import-export', () => import('./page/sw-import-export'));
Shopware.Component.register('sw-import-export-exporter', () => import('./component/sw-import-export-exporter'));
Shopware.Component.register('sw-import-export-importer', () => import('./component/sw-import-export-importer'));
Shopware.Component.register('sw-import-export-activity', () => import('./component/sw-import-export-activity'));
Shopware.Component.register('sw-import-export-activity-log-info-modal', () => import('./component/sw-import-export-activity-log-info-modal'));
Shopware.Component.register('sw-import-export-activity-result-modal', () => import('./component/sw-import-export-activity-result-modal'));
Shopware.Component.register('sw-import-export-edit-profile-modal', () => import('./component/sw-import-export-edit-profile-modal'));
Shopware.Component.register('sw-import-export-edit-profile-modal-mapping', () => import('./component/sw-import-export-edit-profile-modal-mapping'));
Shopware.Component.register('sw-import-export-edit-profile-modal-identifiers', () => import('./component/sw-import-export-edit-profile-modal-identifiers'));
Shopware.Component.register('sw-import-export-entity-path-select', () => import('./component/sw-import-export-entity-path-select'));
Shopware.Component.register('sw-import-export-edit-profile-field-indicators', () => import('./component/sw-import-export-edit-profile-field-indicators'));
Shopware.Component.register('sw-import-export-edit-profile-import-settings', () => import('./component/sw-import-export-edit-profile-import-settings'));
Shopware.Component.register('sw-import-export-edit-profile-general', () => import('./component/sw-import-export-edit-profile-general'));
Shopware.Component.register('sw-import-export-new-profile-wizard', () => import('./component/profile-wizard/sw-import-export-new-profile-wizard'));
Shopware.Component.register('sw-import-export-new-profile-wizard-general-page', () => import('./component/profile-wizard/sw-import-export-new-profile-wizard-general-page'));
Shopware.Component.register('sw-import-export-new-profile-wizard-csv-page', () => import('./component/profile-wizard/sw-import-export-new-profile-wizard-csv-page'));
Shopware.Component.register('sw-import-export-new-profile-wizard-mapping-page', () => import('./component/profile-wizard/sw-import-export-new-profile-wizard-mapping-page'));
Shopware.Component.register('sw-import-export-view-import', () => import('./view/sw-import-export-view-import'));
Shopware.Component.register('sw-import-export-view-export', () => import('./view/sw-import-export-view-export'));
Shopware.Component.register('sw-import-export-view-profiles', () => import('./view/sw-import-export-view-profiles'));
Shopware.Component.register('sw-import-export-progress', () => import('./component/sw-import-export-progress'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-import-export', {
    type: 'core',
    name: 'ImportExport',
    title: 'sw-import-export.general.mainMenuItemGeneral',
    description: 'sw-import-export.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
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
        group: 'shop',
        to: 'sw.import.export.index',
        icon: 'regular-database',
        privilege: 'system.import_export',
    },
});
