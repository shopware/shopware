import { Module } from 'src/core/shopware';

import './extension/sw-settings-index';
import './page/sw-settings-number-range-list';
import './page/sw-settings-number-range-detail';
import './page/sw-settings-number-range-create';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-number-range', {
    type: 'core',
    name: 'sw-settings-number-range.general.mainMenuItemGeneral',
    description: 'Number Range section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'number_range',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-number-range-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-number-range-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.number.range.index'
            }
        },
        create: {
            component: 'sw-settings-number-range-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.number.range.index'
            }
        }
    },

    navigation: [{
        label: 'sw-settings-number-range.general.mainMenuItemGeneral',
        id: 'sw-settings-number-range',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.number.range.index',
        parent: 'sw-settings'
    }]
});
