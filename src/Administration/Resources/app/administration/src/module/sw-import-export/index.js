import './page/sw-import-export-index';
import './page/sw-import-export-profile-list';
import './page/sw-import-export-profile-create';
import './page/sw-import-export-profile-detail';
import './page/sw-import-export-log-list';
import './component/sw-import-export-log-modal';
import './component/sw-import-export-profile-csv-mapping-modal';
import './component/sw-import-export-progress';
import { NEXT733 } from 'src/flag/feature_next733';

const { Module } = Shopware;

Module.register('sw-import-export', {
    type: 'core',
    name: 'Import/Export',
    flag: NEXT733,
    title: 'sw-import-export.general.mainMenuItemGeneral',
    description: 'sw-import-export.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#f198e4',
    icon: 'default-device-database',
    entity: 'import_export_profile',

    routes: {
        index: {
            component: 'sw-import-export-index',
            path: 'index'
        },
        protocol: {
            component: 'sw-import-export-log-list',
            path: 'protocol'
        },
        profile_index: {
            component: 'sw-import-export-profile-list',
            path: 'profile/index'
        },
        profile_create: {
            component: 'sw-import-export-profile-create',
            path: 'profile/create',
            meta: {
                parentPath: 'sw.import.export.profile_index'
            }
        },
        profile_detail: {
            component: 'sw-import-export-profile-detail',
            path: 'profile/detail/:id',
            meta: {
                parentPath: 'sw.import.export.profile_index'
            }
        }
    },

    navigation: [{
        id: 'sw-import-export',
        label: 'sw-import-export.general.mainMenuItemGeneral',
        color: '#f198e4',
        path: 'sw.import.export.index',
        icon: 'default-device-database',
        position: 90
    }, {
        path: 'sw.import.export.index',
        label: 'sw-import-export.general.mainMenuItemGeneral',
        parent: 'sw-import-export'
    }, {
        path: 'sw.import.export.protocol',
        label: 'sw-import-export.general.mainMenuItemLogs',
        parent: 'sw-import-export'
    }, {
        path: 'sw.import.export.profile_index',
        label: 'sw-import-export.general.mainMenuItemProfiles',
        parent: 'sw-import-export'
    }]
});
