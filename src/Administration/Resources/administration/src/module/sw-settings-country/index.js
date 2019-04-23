import { Module } from 'src/core/shopware';

import './extension/sw-settings-index';
import './page/sw-settings-country-list';
import './page/sw-settings-country-detail';
import './page/sw-settings-country-create';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-country', {
    type: 'core',
    name: 'sw-settings-country.general.mainMenuItemGeneral',
    description: 'Country section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'country',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-country-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-country-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.country.index'
            }
        },
        create: {
            component: 'sw-settings-country-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.country.index'
            }
        }
    },

    navigation: [{
        label: 'sw-settings-country.general.mainMenuItemGeneral',
        id: 'sw-settings-country',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.country.index',
        parent: 'sw-settings'
    }]
});
