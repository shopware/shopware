import { Module } from 'src/core/shopware';

import './extension/sw-settings-index';
import './page/sw-settings-snippet-set-list';
import './page/sw-settings-snippet-list';
import './page/sw-settings-snippet-detail';
import './page/sw-settings-snippet-create';
import './component/sidebar/sw-settings-snippet-sidebar';
import './component/sidebar/sw-settings-snippet-boolean-filter-item';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-snippet', {
    type: 'core',
    name: 'sw-settings-snippet.general.mainMenuItemGeneral',
    description: 'sw-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'snippet',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

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
        color: '#9AA8B5',
        path: 'sw.settings.snippet.index',
        parent: 'sw-settings'
    }]
});
