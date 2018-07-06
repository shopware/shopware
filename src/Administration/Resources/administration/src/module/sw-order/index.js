import { Module } from 'src/core/shopware';
import './page/sw-order-list';
import './page/sw-order-detail';
import './view/sw-order-detail-base';
import './view/sw-order-detail-deliveries';
import './component/sw-order-line-items-grid';
import './component/sw-order-delivery-line-items-grid';
import './component/sw-order-delivery';

Module.register('sw-order', {
    type: 'core',
    name: 'Orders',
    description: 'sw-order.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#A092F0',
    icon: 'default-shopping-paper-bag',

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
        icon: 'default-shopping-paper-bag'
    }, {
        path: 'sw.order.index',
        label: 'sw-order.general.mainMenuItemList',
        parent: 'sw-order'
    }]
});
