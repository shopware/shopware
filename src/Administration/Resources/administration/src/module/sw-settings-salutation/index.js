import { Module } from 'src/core/shopware';
import './extension/sw-settings-index';
import './page/sw-settings-salutation-list';
import './page/sw-settings-salutation-detail';
import './page/sw-settings-salutation-create';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-salutation', {
    type: 'core',
    name: 'sw-settings-salutation.general.mainMenuItemGeneral',
    description: 'sw-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'salutation',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-salutation-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-salutation-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.salutation.index'
            }
        },
        create: {
            component: 'sw-settings-salutation-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.salutation.index'
            }
        }
    },

    navigation: [{
        id: 'sw-settings-salutation',
        icon: 'default-action-settings',
        label: 'sw-settings-salutation.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        path: 'sw.settings.salutation.index',
        parent: 'sw-settings'
    }]
});
