import { Module } from 'src/core/shopware';
import { NEXT717 } from 'src/flag/feature_next717';

import './extension/sw-settings-index';
import './page/sw-settings-snippet-set-list';
import './page/sw-settings-snippet-detail';
import './page/sw-settings-snippet-create';

Module.register('sw-settings-snippet', {
    flag: NEXT717,
    type: 'core',
    name: 'Snippets',
    description: 'The shopware snippet module',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#2471C8',
    icon: 'default-action-settings',

    routes: {
        index: {
            component: 'sw-settings-snippet-set-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-snippet-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.snippet.index'
            }
        },
        create: {
            component: 'sw-settings-snippet-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.snippet.index'
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
