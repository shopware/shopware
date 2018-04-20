import { Module } from 'src/core/shopware';
import './page/sw-customer-list';
import './page/sw-customer-detail';
import './page/view/sw-customer-detail-base';

Module.register('sw-customer', {
    type: 'core',
    name: 'moduleNames.customers',
    description: 'The module for managing customers.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F88962',
    icon: 'default-avatar-multiple',

    routes: {
        index: {
            components: {
                default: 'sw-customer-list'
            },
            path: 'index/:offset?/:limit?/:sortBy?/:sortDirection?/:term?/:filters?'
        },

        detail: {
            component: 'sw-customer-detail',
            path: 'detail/:id',
            redirect: {
                name: 'sw.customer.detail.base'
            },
            children: {
                base: {
                    component: 'sw-customer-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.customer.index'
                    }
                }
            }
        }
    },

    navigation: [{
        path: 'sw.customer.index',
        label: 'sw-customer.general.mainMenuItemList',
        color: '#F88962',
        icon: 'default-avatar-multiple'
    }]
});
