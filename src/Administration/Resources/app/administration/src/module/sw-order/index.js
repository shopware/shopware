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
import './component/sw-order-state-change-modal/sw-order-state-change-modal-attach-documents';
import './component/sw-order-state-change-modal/sw-order-state-change-modal-assign-mail-template';
import './component/sw-order-state-history-card';
import './component/sw-order-state-history-card-entry';
import './component/sw-order-state-select';
import './component/sw-order-inline-field';
import './component/sw-order-user-card';
import './component/sw-order-document-card';

const { Module } = Shopware;

Module.register('sw-order', {
    type: 'core',
    name: 'order',
    title: 'sw-order.general.mainMenuItemGeneral',
    description: 'sw-order.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#A092F0',
    icon: 'default-shopping-paper-bag',
    favicon: 'icon-module-orders.png',
    entity: 'order',

    routes: {
        index: {
            components: {
                default: 'sw-order-list'
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
