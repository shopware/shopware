import { Module } from 'src/core/shopware';
import { NEXT681 } from 'src/flag/feature_next681';

import './extension/sw-settings-index';
import './page/sw-settings-user-list';
import './page/sw-settings-user-detail';
import './page/sw-settings-user-create';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-user', {
    type: 'core',
    flag: NEXT681,
    name: 'sw-settings-user.general.mainMenuItemGeneral',
    description: 'sw-settings-user.general.mainMenuItemGeneral',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'user',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        list: {
            component: 'sw-settings-user-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-user-detail',
            path: 'detail/:id?',
            meta: {
                parentPath: 'sw.settings.user.list'
            }
        },
        create: {
            component: 'sw-settings-user-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.user.list'
            }
        }
    },

    navigation: [{
        id: 'sw-settings-user',
        label: 'sw-settings-user.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.user.list',
        parent: 'sw-settings'
    }]
});
