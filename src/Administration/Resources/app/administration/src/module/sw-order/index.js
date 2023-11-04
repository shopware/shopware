import './acl';

import './mixin/cart-notification.mixin';

import defaultSearchConfiguration from './default-search-configuration';

/**
 * @package customer-order
 */

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-order-list', () => import('./page/sw-order-list'));
Shopware.Component.register('sw-order-detail', () => import('./page/sw-order-detail'));
Shopware.Component.register('sw-order-create', () => import('./page/sw-order-create'));
Shopware.Component.register('sw-order-detail-general', () => import('./view/sw-order-detail-general'));
Shopware.Component.register('sw-order-detail-details', () => import('./view/sw-order-detail-details'));
Shopware.Component.register('sw-order-detail-documents', () => import('./view/sw-order-detail-documents'));
Shopware.Component.register('sw-order-create-base', () => import('./view/sw-order-create-base'));
Shopware.Component.register('sw-order-create-initial', () => import('./view/sw-order-create-initial'));
Shopware.Component.register('sw-order-create-general', () => import('./view/sw-order-create-general'));
Shopware.Component.register('sw-order-create-details', () => import('./view/sw-order-create-details'));
Shopware.Component.register('sw-order-nested-line-items-modal', () => import('./component/sw-order-nested-line-items-modal'));
Shopware.Component.register('sw-order-nested-line-items-row', () => import('./component/sw-order-nested-line-items-row'));
Shopware.Component.register('sw-order-line-items-grid', () => import('./component/sw-order-line-items-grid'));
Shopware.Component.register('sw-order-line-items-grid-sales-channel', () => import('./component/sw-order-line-items-grid-sales-channel'));
Shopware.Component.register('sw-order-delivery-metadata', () => import('./component/sw-order-delivery-metadata'));
Shopware.Component.register('sw-order-customer-comment', () => import('./component/sw-order-customer-comment'));
Shopware.Component.register('sw-order-product-select', () => import('./component/sw-order-product-select'));
Shopware.Component.register('sw-order-saveable-field', () => import('./component/sw-order-saveable-field'));
Shopware.Component.register('sw-order-address-modal', () => import('./component/sw-order-address-modal'));
Shopware.Component.register('sw-order-address-selection', () => import('./component/sw-order-address-selection'));
Shopware.Component.register('sw-order-leave-page-modal', () => import('./component/sw-order-leave-page-modal'));
Shopware.Component.register('sw-order-state-change-modal-attach-documents', () => import('./component/sw-order-state-change-modal/sw-order-state-change-modal-attach-documents'));
Shopware.Component.register('sw-order-state-history-card', () => import('./component/sw-order-state-history-card'));
Shopware.Component.register('sw-order-state-history-card-entry', () => import('./component/sw-order-state-history-card-entry'));
Shopware.Component.register('sw-order-state-history-modal', () => import('./component/sw-order-state-history-modal'));
Shopware.Component.register('sw-order-state-change-modal', () => import('./component/sw-order-state-change-modal'));
Shopware.Component.register('sw-order-state-select-v2', () => import('./component/sw-order-state-select-v2'));
Shopware.Component.register('sw-order-details-state-card', () => import('./component/sw-order-details-state-card'));
Shopware.Component.register('sw-order-inline-field', () => import('./component/sw-order-inline-field'));
Shopware.Component.register('sw-order-user-card', () => import('./component/sw-order-user-card'));
Shopware.Component.register('sw-order-document-card', () => import('./component/sw-order-document-card'));
Shopware.Component.register('sw-order-document-settings-modal', () => import('./component/sw-order-document-settings-modal'));
Shopware.Component.extend('sw-order-document-settings-invoice-modal', 'sw-order-document-settings-modal', () => import('./component/sw-order-document-settings-invoice-modal'));
Shopware.Component.extend('sw-order-document-settings-storno-modal', 'sw-order-document-settings-modal', () => import('./component/sw-order-document-settings-storno-modal'));
Shopware.Component.extend('sw-order-document-settings-delivery-note-modal', 'sw-order-document-settings-modal', () => import('./component/sw-order-document-settings-delivery-note-modal'));
Shopware.Component.extend('sw-order-document-settings-credit-note-modal', 'sw-order-document-settings-modal', () => import('./component/sw-order-document-settings-credit-note-modal'));
Shopware.Component.register('sw-order-create-details-header', () => import('./component/sw-order-create-details-header'));
Shopware.Component.register('sw-order-create-details-body', () => import('./component/sw-order-create-details-body'));
Shopware.Component.register('sw-order-create-details-footer', () => import('./component/sw-order-create-details-footer'));
Shopware.Component.register('sw-order-create-address-modal', () => import('./component/sw-order-create-address-modal'));
Shopware.Component.register('sw-order-new-customer-modal', () => import('./component/sw-order-new-customer-modal'));
Shopware.Component.register('sw-order-promotion-field', () => import('./component/sw-order-promotion-field'));
Shopware.Component.extend('sw-order-promotion-tag-field', 'sw-tagged-field', () => import('./component/sw-order-promotion-tag-field'));
Shopware.Component.register('sw-order-create-invalid-promotion-modal', () => import('./component/sw-order-create-invalid-promotion-modal'));
Shopware.Component.register('sw-order-create-promotion-modal', () => import('./component/sw-order-create-promotion-modal'));
Shopware.Component.register('sw-order-create-general-info', () => import('./component/sw-order-create-general-info'));
Shopware.Component.register('sw-order-select-document-type-modal', () => import('./component/sw-order-select-document-type-modal'));
Shopware.Component.register('sw-order-general-info', () => import('./component/sw-order-general-info'));
Shopware.Component.register('sw-order-send-document-modal', () => import('./component/sw-order-send-document-modal'));
Shopware.Component.register('sw-order-create-initial-modal', () => import('./component/sw-order-create-initial-modal'));
Shopware.Component.register('sw-order-customer-grid', () => import('./component/sw-order-customer-grid'));
Shopware.Component.register('sw-order-create-options', () => import('./component/sw-order-create-options'));
Shopware.Component.register('sw-order-customer-address-select', () => import('./component/sw-order-customer-address-select'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

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
                name: 'sw.order.create.initial',
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
                name: 'sw.order.detail.general',
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

function orderCreateChildren() {
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
