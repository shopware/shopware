import { Module } from 'src/core/shopware';

import './extension/sw-settings-wrapper-index';
import './page/sw-settings-country-list';
import './page/sw-settings-country-detail';
import './page/sw-settings-country-create';

Module.register('sw-settings-country', {
    type: 'core',
    name: 'Currency settings',
    description: 'Tax section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',

    routes: {
        index: {
            component: 'sw-settings-country-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.wrapper.index'
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
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.country.index',
        parent: 'sw-settings-wrapper'
    }]
});
