import './page/sw-customer-list';
import './page/sw-customer-detail';
import './page/sw-customer-create';
import './view/sw-customer-detail-base';
import './view/sw-customer-detail-addresses';
import './view/sw-customer-detail-order';
import './component/sw-customer-base-form';
import './component/sw-customer-base-info';
import './component/sw-customer-address-form';
import './component/sw-customer-address-form-options';
import './component/sw-customer-default-addresses';
import './component/sw-customer-card';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-customer', {
    type: 'core',
    name: 'customers',
    title: 'sw-customer.general.mainMenuItemGeneral',
    description: 'sw-customer.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F88962',
    icon: 'default-avatar-multiple',
    favicon: 'icon-module-customers.png',
    entity: 'customer',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            components: {
                default: 'sw-customer-list'
            },
            path: 'index'
        },

        create: {
            component: 'sw-customer-create',
            path: 'create',
            meta: {
                parentPath: 'sw.customer.index'
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
                    path: 'base',
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
                },
                order: {
                    component: 'sw-customer-detail-order',
                    path: 'order',
                    meta: {
                        parentPath: 'sw.customer.index'
                    }
                }
            },

            props: {
                default(route) {
                    return {
                        customerId: route.params.id
                    };
                }
            }
        }
    },

    navigation: [{
        id: 'sw-customer',
        label: 'sw-customer.general.mainMenuItemGeneral',
        color: '#F88962',
        path: 'sw.customer.index',
        icon: 'default-avatar-multiple',
        position: 40
    }, {
        path: 'sw.customer.index',
        label: 'sw-customer.general.mainMenuItemList',
        color: '#F88962',
        icon: 'default-avatar-multiple',
        parent: 'sw-customer'
    }]
});
