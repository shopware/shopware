import { Module } from 'src/core/shopware';
import './extension/sw-settings-index';
import './page/sw-settings-customer-group-list';
import './page/sw-settings-customer-group-detail';
import './page/sw-settings-customer-group-create';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-customer-group', {
    type: 'core',
    name: 'sw-settings-customer-group.general.mainMenuItemGeneral',
    description: 'sw-settings-customer-group.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'customer_group',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-customer-group-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-customer-group-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.customer.group.index'
            }
        },
        create: {
            component: 'sw-settings-customer-group-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.customer.group.index'
            }
        }
    },

    navigation: [{
        id: 'sw-settings-customer-group',
        icon: 'default-action-settings',
        label: 'sw-settings-customer-group.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        path: 'sw.settings.customer.group.index',
        parent: 'sw-settings'
    }]
});
