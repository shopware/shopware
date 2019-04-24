import { Module } from 'src/core/shopware';
import { NEXT688 } from 'src/flag/feature_next688';

import './extension/sw-settings-index';
import './page/sw-settings-shipping-list';
import './page/sw-settings-shipping-detail';
import './page/sw-settings-shipping-create';
import './component/sw-price-rule-modal';
import './component/sw-settings-shipping-price-matrices';
import './component/sw-settings-shipping-price-matrix';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-shipping', {
    type: 'core',
    flag: NEXT688,
    name: 'sw-settings-shipping.general.mainMenuItemGeneral',
    description: 'Shipping section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'shipping_method',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

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
            path: 'detail/:id'
        },
        create: {
            component: 'sw-settings-shipping-create',
            path: 'create',
            redirect: {
                name: 'sw.settings.shipping.create.base'
            },
            meta: {
                parentPath: 'sw.settings.shipping.index'
            },
            children: {
                base: {
                    component: 'sw-settings-shipping-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.settings.shipping.index'
                    }
                },
                advancedPrices: {
                    component: 'sw-settings-shipping-detail-advanced-prices',
                    path: 'advancedPrices',
                    meta: {
                        parentPath: 'sw.settings.shipping.index'
                    }
                }
            }
        }
    },
    navigation: [{
        label: 'sw-settings-shipping.general.mainMenuItemGeneral',
        id: 'sw-settings-shipping',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.shipping.index',
        parent: 'sw-settings'
    }]
});
