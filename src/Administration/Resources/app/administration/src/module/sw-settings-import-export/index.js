import ImportExportService from './service/importExport.service';
import './extension/sw-settings-index';
import './page/sw-settings-import-export';
import './component/sw-settings-import-export-exporter';
import './component/sw-settings-import-export-importer';
import './component/sw-settings-import-export-activity';
import './component/sw-settings-import-export-edit-profile-modal';
import './component/sw-settings-import-export-edit-profile-modal-mapping';
import './component/sw-import-export-entity-path-select';
import './view/sw-settings-import-export-view-import';
import './view/sw-settings-import-export-view-export';
import './view/sw-settings-import-export-view-profiles';
import './component/sw-settings-import-export-progress';


Shopware.Service().register('importExport', () => {
    return new ImportExportService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

Shopware.Module.register('sw-settings-import-export', {
    type: 'core',
    name: 'Import/Export',
    title: 'sw-settings-import-export.general.mainMenuItemGeneral',
    description: 'sw-settings-import-export.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-device-database',
    entity: 'import_export_profile',
    routePrefixPath: 'sw/settings/import-export',

    routes: {
        index: {
            component: 'sw-settings-import-export',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            },
            redirect: {
                name: 'sw.settings.import.export.index.import'
            },

            children: {
                import: {
                    component: 'sw-settings-import-export-view-import',
                    path: 'import',
                    meta: {
                        parentPath: 'sw.settings.index'
                    }
                },
                export: {
                    component: 'sw-settings-import-export-view-export',
                    path: 'export',
                    meta: {
                        parentPath: 'sw.settings.index'
                    }
                },
                profiles: {
                    component: 'sw-settings-import-export-view-profiles',
                    path: 'profiles',
                    meta: {
                        parentPath: 'sw.settings.index'
                    }
                }
            }
        }
    }
});
