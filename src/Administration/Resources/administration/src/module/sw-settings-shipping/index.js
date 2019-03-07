import { Module } from 'src/core/shopware';
import { NEXT688 } from 'src/flag/feature_next688';

import './extension/sw-settings-index';
import './page/sw-settings-shipping-list';
import './page/sw-settings-shipping-detail';
import './page/sw-settings-shipping-create';
import './view/sw-settings-shipping-detail-base';
import './view/sw-settings-shipping-detail-advanced-prices';

Module.register('sw-settings-shipping', {
    type: 'core',
    flag: NEXT688,
    name: 'Shipping settings',
    description: 'Shipping section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    entity: 'shipping_method',

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
            redirect: {
                name: 'sw.settings.shipping.detail.base'
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
