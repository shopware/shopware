import './acl';

import './mixin/cart-notification.mixin';
import './mixin/order-cart.mixin';

import './page/sw-order-list';
import './page/sw-order-detail';
import './page/sw-order-create';

import './view/sw-order-detail-base';
import './view/sw-order-detail-general';
import './view/sw-order-detail-details';
import './view/sw-order-detail-documents';
import './view/sw-order-create-base';
import './view/sw-order-create-initial';
import './view/sw-order-create-general';
import './view/sw-order-create-details';

import './component/sw-order-nested-line-items-modal';
import './component/sw-order-nested-line-items-row';

import './component/sw-order-line-items-grid';
import './component/sw-order-line-items-grid-sales-channel';
import './component/sw-order-delivery-metadata';
import './component/sw-order-customer-comment';
import './component/sw-order-product-select';
import './component/sw-order-saveable-field';
import './component/sw-order-address-modal';
import './component/sw-order-address-selection';
import './component/sw-order-leave-page-modal';
import './component/sw-order-state-change-modal/sw-order-state-change-modal-attach-documents';
import './component/sw-order-state-history-card';
import './component/sw-order-state-history-card-entry';
import './component/sw-order-state-history-modal';
import './component/sw-order-state-select';
import './component/sw-order-state-select-v2';
import './component/sw-order-details-state-card';
import './component/sw-order-inline-field';
import './component/sw-order-user-card';
import './component/sw-order-document-card';
import './component/sw-order-create-details-header';
import './component/sw-order-create-details-body';
import './component/sw-order-create-details-footer';
import './component/sw-order-create-address-modal';
import './component/sw-order-new-customer-modal';
import './component/sw-order-promotion-field';
import './component/sw-order-promotion-tag-field';
import './component/sw-order-create-invalid-promotion-modal';
import './component/sw-order-create-promotion-modal';
import './component/sw-order-create-general-info';
import './component/sw-order-select-document-type-modal';
import './component/sw-order-general-info';
import './component/sw-order-send-document-modal';
import '../sw-customer/component/sw-customer-address-form';
import '../sw-customer/component/sw-customer-address-form-options';

import './component/sw-order-create-initial-modal';
import './component/sw-order-customer-grid';
import './component/sw-order-create-options';
import './component/sw-order-customer-address-select';

import defaultSearchConfiguration from './default-search-configuration';

/**
 * @package customer-order
 */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-order', {
    type: 'core',
    name: 'order',
    title: 'sw-order.general.mainMenuItemGeneral',
    description: 'sw-order.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#A092F0',
    icon: 'regular-shopping-bag',
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
                name: Shopware.Feature.isActive('FEATURE_NEXT_7530')
                    ? 'sw.order.create.initial'
                    : 'sw.order.create.base',
            },
            meta: {
                privilege: 'order.creator',
            },
            children: orderCreateChildren(),
        },

        detail: {
            component: 'sw-order-detail',
            path: 'detail/:id',
            redirect: {
                name: Shopware.Feature.isActive('FEATURE_NEXT_7530')
                    ? 'sw.order.detail.general'
                    : 'sw.order.detail.base',
            },
            meta: {
                privilege: 'order.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            children: orderDetailChildren(),
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
        icon: 'regular-shopping-bag',
        position: 30,
        privilege: 'order.viewer',
    }, {
        path: 'sw.order.index',
        label: 'sw-order.general.mainMenuItemList',
        parent: 'sw-order',
        privilege: 'order.viewer',
    }],

    defaultSearchConfiguration,
});


function orderDetailChildren() {
    if (Shopware.Feature.isActive('FEATURE_NEXT_7530')) {
        return {
            general: {
                component: 'sw-order-detail-general',
                path: 'general',
                meta: {
                    parentPath: 'sw.order.index',
                    privilege: 'order.viewer',
                },
            },
            details: {
                component: 'sw-order-detail-details',
                path: 'details',
                meta: {
                    parentPath: 'sw.order.index',
                    privilege: 'order.viewer',
                },
            },
            documents: {
                component: 'sw-order-detail-documents',
                path: 'documents',
                meta: {
                    parentPath: 'sw.order.index',
                    privilege: 'order.viewer',
                },
            },
        };
    }

    return {
        base: {
            component: 'sw-order-detail-base',
            path: 'base',
            meta: {
                parentPath: 'sw.order.index',
                privilege: 'order.viewer',
            },
        },
    };
}

function orderCreateChildren() {
    if (Shopware.Feature.isActive('FEATURE_NEXT_7530')) {
        return {
            initial: {
                component: 'sw-order-create-initial',
                path: 'initial',
                meta: {
                    parentPath: 'sw.order.index',
                    privilege: 'order.creator',
                },
            },
            general: {
                component: 'sw-order-create-general',
                path: 'general',
                meta: {
                    parentPath: 'sw.order.index',
                    privilege: 'order.creator',
                },
            },
            details: {
                component: 'sw-order-create-details',
                path: 'details',
                meta: {
                    parentPath: 'sw.order.index',
                    privilege: 'order.creator',
                },
            },
        };
    }

    return {
        base: {
            component: 'sw-order-create-base',
            path: 'base',
            meta: {
                parentPath: 'sw.order.index',
                privilege: 'order.viewer',
            },
        },
    };
}
