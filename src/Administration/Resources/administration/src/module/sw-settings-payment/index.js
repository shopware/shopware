import { Module } from 'src/core/shopware';

import './extension/sw-settings-wrapper-index';
import './page/sw-settings-payment-list';
import './page/sw-settings-payment-detail';
import './page/sw-settings-payment-create';

Module.register('sw-settings-payment', {
    type: 'core',
    name: 'Payment settings',
    description: 'Payment section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',

    routes: {
        index: {
            component: 'sw-settings-payment-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.wrapper.index'
            }
        },
        detail: {
            component: 'sw-settings-payment-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.payment.index'
            }
        },
        create: {
            component: 'sw-settings-payment-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.payment.index'
            }
        }
    },

    navigation: [{
        label: 'sw-settings-payment.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.payment.index',
        parent: 'sw-settings-wrapper'
    }]
});
