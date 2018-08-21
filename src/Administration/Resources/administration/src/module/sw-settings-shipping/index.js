import { Module } from 'src/core/shopware';

import './extension/sw-settings-index';
import './page/sw-settings-shipping-list';
import './page/sw-settings-shipping-detail';
import './page/sw-settings-shipping-create';

Module.register('sw-settings-shipping', {
    type: 'core',
    name: 'Currency settings',
    description: 'Tax section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',

    routes: {
        index: {
            component: 'sw-settings-shipping-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-shipping-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.shipping.index'
            }
        },
        create: {
            component: 'sw-settings-shipping-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.shipping.index'
            }
        }
    },

    navigation: [{
        label: 'sw-settings-shipping.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.shipping.index',
        parent: 'sw-settings'
    }]
});
