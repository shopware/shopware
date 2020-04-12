import ImportExportService from './service/importExport.service';
import './page/sw-import-export';
import './component/sw-import-export-exporter';
import './component/sw-import-export-importer';
import './component/sw-import-export-activity';
import './component/sw-import-export-activity-detail-modal';
import './component/sw-import-export-edit-profile-modal';
import './component/sw-import-export-edit-profile-modal-mapping';
import './component/sw-import-export-entity-path-select';
import './view/sw-import-export-view-import';
import './view/sw-import-export-view-export';
import './view/sw-import-export-view-profiles';
import './component/sw-import-export-progress';

Shopware.Service().register('importExport', () => {
    return new ImportExportService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

Shopware.Module.register('sw-import-export', {
    type: 'core',
    name: 'ImportExport',
    title: 'sw-import-export.general.mainMenuItemGeneral',
    description: 'sw-import-export.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-device-database',
    entity: 'import_export_profile',
    routePrefixPath: 'sw/import-export',

    routes: {
        index: {
            component: 'sw-import-export',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            },
            redirect: {
                name: 'sw.import.export.index.import'
            },

            children: {
                import: {
                    component: 'sw-import-export-view-import',
                    path: 'import',
                    meta: {
                        parentPath: 'sw.settings.index'
                    }
                },
                export: {
                    component: 'sw-import-export-view-export',
                    path: 'export',
                    meta: {
                        parentPath: 'sw.settings.index'
                    }
                },
                profiles: {
                    component: 'sw-import-export-view-profiles',
                    path: 'profiles',
                    meta: {
                        parentPath: 'sw.settings.index'
                    }
                }
            }
        }
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.import.export.index',
        icon: 'default-location-flag'
    }
});
