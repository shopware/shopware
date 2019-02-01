import { Module } from 'src/core/shopware';
import './page/sw-order-list';
import './page/sw-order-detail';
import './view/sw-order-detail-base';
import './component/sw-order-line-items-grid';
import './component/sw-order-delivery-metadata';
import './component/sw-order-product-select';
import './component/sw-order-saveable-field';
import './../sw-customer/component/sw-customer-address-form';
import './component/sw-order-address-modal';
import './component/sw-order-leave-page-modal';

Module.register('sw-order', {
    type: 'core',
    name: 'Orders',
    description: 'sw-order.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#A092F0',
    icon: 'default-shopping-paper-bag',
    entity: 'order',

    routes: {
        index: {
            components: {
                default: 'sw-order-list',
                sidebar: 'sw-order-sidebar'
            },
            path: 'index'
        },

        detail: {
            component: 'sw-order-detail',
            path: 'detail/:id',
            redirect: {
                name: 'sw.order.detail.base'
            },
            children: {
                base: {
                    component: 'sw-order-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.order.index'
                    }
                },
                deliveries: {
                    component: 'sw-order-detail-deliveries',
                    path: 'deliveries',
                    meta: {
                        parentPath: 'sw.order.index'
                    }
                }
            }
        }
    },

    navigation: [{
        id: 'sw-order',
        label: 'sw-order.general.mainMenuItemGeneral',
        color: '#A092F0',
        path: 'sw.order.index',
        icon: 'default-shopping-paper-bag',
        position: 30
    }, {
        path: 'sw.order.index',
        label: 'sw-order.general.mainMenuItemList',
        parent: 'sw-order'
    }]
});
