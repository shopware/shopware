import { Module } from 'src/core/shopware';

import './extension/sw-settings-index';
import './page/sw-settings-tax-list';
import './page/sw-settings-tax-detail';
import './page/sw-settings-tax-create';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-tax', {
    type: 'core',
    name: 'sw-settings-tax.general.mainMenuItemGeneral',
    description: 'Tax section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'tax',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-tax-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-tax-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.tax.index'
            }
        },
        create: {
            component: 'sw-settings-tax-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.tax.index'
            }
        }
    },

    navigation: [{
        label: 'sw-settings-tax.general.mainMenuItemGeneral',
        id: 'sw-settings-tax',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.tax.index',
        parent: 'sw-settings'
    }]
});
