import { Module } from 'src/core/shopware';

import './extension/sw-settings-index';
import './page/sw-settings-shipping-list';
import './page/sw-settings-shipping-detail';
import './page/sw-settings-shipping-create';
import './component/sw-price-rule-modal';
import './component/sw-settings-shipping-price-matrices';
import './component/sw-settings-shipping-price-matrix';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Module.register('sw-settings-shipping', {
    type: 'core',
    name: 'settings-shipping',
    title: 'sw-settings-shipping.general.mainMenuItemGeneral',
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
    }
});
