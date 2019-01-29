import { Module } from 'src/core/shopware';
import { NEXT717 } from 'src/flag/feature_next717';

import './extension/sw-settings-index';
import './page/sw-settings-snippet-set-list';
import './page/sw-settings-snippet-list';
import './page/sw-settings-snippet-detail';
import './page/sw-settings-snippet-create';
import './component/sidebar/sw-settings-snippet-sidebar';
import './component/sidebar/sw-settings-snippet-boolean-filter-item';

Module.register('sw-settings-snippet', {
    flag: NEXT717,
    type: 'core',
    name: 'Snippets',
    description: 'sw-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#2471C8',
    icon: 'default-action-settings',
    entity: 'snippet',

    routes: {
        index: {
            component: 'sw-settings-snippet-set-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        list: {
            component: 'sw-settings-snippet-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.snippet.index'
            }
        },
        detail: {
            component: 'sw-settings-snippet-detail',
            path: 'detail/:key',
            meta: {
                parentPath: 'sw.settings.snippet.list'
            }
        },
        create: {
            component: 'sw-settings-snippet-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.snippet.list'
            }
        }
    },

    navigation: [{
        id: 'sw-settings-snippet',
        icon: 'default-action-settings',
        label: 'sw-settings-snippet.general.mainMenuItemGeneral',
        color: '#2471C8',
        path: 'sw.settings.snippet.index',
        parent: 'sw-settings'
    }]
});
