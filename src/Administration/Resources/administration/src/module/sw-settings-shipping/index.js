import { Module } from 'src/core/shopware';

import './extension/sw-settings-index';
import './page/sw-settings-shipping-list';
import './page/sw-settings-shipping-detail';
import './page/sw-settings-shipping-create';

Module.register('sw-settings-shipping', {
    type: 'core',
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
    }
});
