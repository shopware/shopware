import { Module } from 'src/core/shopware';
// import './component/sw-product-basic-form';
// import './component/sw-product-category-form';
// import './component/sw-product-price-form';
// import './component/sw-product-settings-form';
// import './view/sw-product-detail-base';
// import './view/sw-product-detail-context-prices';
// import './page/sw-product-detail';
// import './page/sw-product-create';
import './page/sw-customer-list';

Module.register('sw-customer', {
    type: 'core',
    name: 'Customers',
    description: 'The module for managing customer.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F88962',
    icon: 'default-avatar-multiple',

    routes: {
        index: {
            components: {
                default: 'sw-customer-list',
                sidebar: 'sw-customer-sidebar'
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
        label: 'Kunden',
        color: '#F88962',
        icon: 'default-avatar-multiple'
    }],

    commands: [{
        title: 'Übersicht',
        route: 'customer.index'
    }, {
        title: '%0 öffnen',
        route: 'customer.detail'
    }],

    shortcuts: {
        index: {
            mac: {
                title: 'customer.index.shortcut.mac',
                combination: [
                    'CMD',
                    'P'
                ]
            },
            win: {
                title: 'customer.index.shortcut.win',
                combination: [
                    'CTRL',
                    'P'
                ]
            }
        }
    }
});
