import { Module } from 'src/core/shopware';
import './page/sw-customer-list';
import './page/sw-customer-detail';
import './page/sw-customer-create';
import './view/sw-customer-detail-base';
import './view/sw-customer-detail-addresses';
import './component/sw-customer-base-form';
import './component/sw-customer-base-info';
import './component/sw-customer-address-form';
import './component/sw-customer-default-addresses';

Module.register('sw-customer', {
    type: 'core',
    name: 'Customers',
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

        create: {
            component: 'sw-customer-create',
            path: 'create',
            redirect: {
                name: 'sw.customer.create.base'
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
                    path: 'base/:edit?',
                    meta: {
                        parentPath: 'sw.customer.index'
                    }
                },
                addresses: {
                    component: 'sw-customer-detail-addresses',
                    path: 'addresses',
                    meta: {
                        parentPath: 'sw.customer.index'
                    }
                }
            }
        }
    },

    navigation: [{
        id: 'sw-customer',
        label: 'sw-customer.general.mainMenuItemGeneral',
        color: '#F88962',
        icon: 'default-avatar-multiple'
    }, {
        path: 'sw.customer.index',
        label: 'sw-customer.general.mainMenuItemList',
        color: '#F88962',
        icon: 'default-avatar-multiple',
        parent: 'sw-customer'
    }, {
        path: 'sw.customer.create',
        label: 'sw-customer.general.mainMenuItemAdd',
        parent: 'sw-customer',
        color: '#F88962'
    }]
});
