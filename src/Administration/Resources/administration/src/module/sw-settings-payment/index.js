import { Module } from 'src/core/shopware';
import { NEXT687 } from 'src/flag/feature_next687';

import './component/sw-plugin-box';
import './extension/sw-settings-index';
import './page/sw-settings-payment-list';
import './page/sw-settings-payment-detail';
import './page/sw-settings-payment-create';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-payment', {
    type: 'core',
    flag: NEXT687,
    name: 'sw-settings-payment.general.mainMenuItemGeneral',
    description: 'Payment section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'payment_method',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-payment-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
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
        id: 'sw-settings-payment',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.payment.index',
        parent: 'sw-settings'
    }]
});
