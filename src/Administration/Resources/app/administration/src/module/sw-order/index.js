import './acl';

import './page/sw-order-list';
import './page/sw-order-detail';
import './page/sw-order-create';

import './view/sw-order-detail-base';
import './view/sw-order-create-base';

import './component/sw-order-nested-line-items-modal';
import './component/sw-order-nested-line-items-row';

import './component/sw-order-line-items-grid';
import './component/sw-order-line-items-grid-sales-channel';
import './component/sw-order-delivery-metadata';
import './component/sw-order-customer-comment';
import './component/sw-order-product-select';
import './component/sw-order-saveable-field';
import './component/sw-order-address-modal';
import './component/sw-order-leave-page-modal';
import './component/sw-order-state-change-modal/sw-order-state-change-modal-attach-documents';
import './component/sw-order-state-history-card';
import './component/sw-order-state-history-card-entry';
import './component/sw-order-state-select';
import './component/sw-order-inline-field';
import './component/sw-order-user-card';
import './component/sw-order-document-card';
import './component/sw-order-create-details-header';
import './component/sw-order-create-details-body';
import './component/sw-order-create-details-footer';
import './component/sw-order-create-address-modal';
import './component/sw-order-new-customer-modal';
import './component/sw-order-promotion-tag-field';
import './component/sw-order-create-invalid-promotion-modal';
import './component/sw-order-create-promotion-modal';
import '../sw-customer/component/sw-customer-address-form';
import '../sw-customer/component/sw-customer-address-form-options';

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
                default: 'sw-order-list',
            },
            path: 'index',
            meta: {
                privilege: 'order.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        create: {
            component: 'sw-order-create',
            path: 'create',
            redirect: {
                name: 'sw.order.create.base',
            },
            meta: {
                privilege: 'order.creator',
            },
            children: {
                base: {
                    component: 'sw-order-create-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.order.index',
                        privilege: 'order.creator',
                    },
                },
            },
        },

        detail: {
            component: 'sw-order-detail',
            path: 'detail/:id',
            redirect: {
                name: 'sw.order.detail.base',
            },
            meta: {
                privilege: 'order.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            children: {
                base: {
                    component: 'sw-order-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.order.index',
                        privilege: 'order.viewer',
                    },
                },
            },
            props: {
                default: ($route) => {
                    return { orderId: $route.params.id };
                },
            },
        },
    },

    navigation: [{
        id: 'sw-order',
        label: 'sw-order.general.mainMenuItemGeneral',
        color: '#A092F0',
        icon: 'default-shopping-paper-bag',
        position: 30,
        privilege: 'order.viewer',
    }, {
        path: 'sw.order.index',
        label: 'sw-order.general.mainMenuItemList',
        parent: 'sw-order',
        privilege: 'order.viewer',
    }],
});
