import './acl';

import './mixin/cart-notification.mixin';

import defaultSearchConfiguration from './default-search-configuration';
import DocumentV2Service from './service/documentV2.service';
import './component/sw-order-create-document-modal/register';
import './component/sw-order-select-document-type-modal/register';
import './component/sw-order-send-document-modal/register';
import './component/sw-order-upload-document-modal/register';
import './component/sw-order-address-modal/register';
import './component/sw-order-address-selection/register';
import './component/sw-order-create-address-modal/register';
import './component/sw-order-create-details-body/register';
import './component/sw-order-create-details-footer/register';
import './component/sw-order-create-details-header/register';
import './component/sw-order-create-general-info/register';
import './component/sw-order-create-initial-modal/register';
import './component/sw-order-create-invalid-promotion-modal/register';
import './component/sw-order-create-options/register';
import './component/sw-order-create-promotion-modal/register';
import './component/sw-order-customer-address-select/register';
import './component/sw-order-customer-comment/register';
import './component/sw-order-customer-grid/register';
import './component/sw-order-delivery-metadata/register';
import './component/sw-order-details-state-card/register';
import './component/sw-order-document-settings-modal/register';
import './component/sw-order-document-settings-credit-note-modal/register';
import './component/sw-order-document-settings-delivery-note-modal/register';
import './component/sw-order-document-settings-invoice-modal/register';
import './component/sw-order-document-settings-storno-modal/register';
import './component/sw-order-general-info/register';
import './component/sw-order-inline-field/register';
import './component/sw-order-leave-page-modal/register';
import './component/sw-order-line-items-grid/register';
import './component/sw-order-line-items-grid-sales-channel/register';
import './component/sw-order-nested-line-items-modal/register';
import './component/sw-order-nested-line-items-row/register';
import './component/sw-order-new-customer-modal/register';
import './component/sw-order-product-select/register';
import './component/sw-order-promotion-field/register';
import './component/sw-order-promotion-tag-field/register';
import './component/sw-order-save-changes-beforehand-modal/register';
import './component/sw-order-saveable-field/register';
import './component/sw-order-state-change-modal/sw-order-state-change-modal-attach-documents/register';
import './component/sw-order-state-history-card/register';
import './component/sw-order-state-history-card-entry/register';
import './component/sw-order-state-history-modal/register';
import './component/sw-order-state-select-v2/register';
import './component/sw-order-user-card/register';
import './page/sw-order-create/register';
import './view/sw-order-create-base/register';
import './view/sw-order-create-details/register';
import './view/sw-order-create-general/register';
import './view/sw-order-create-initial/register';
import './view/sw-order-detail-details/register';
import './view/sw-order-detail-documents/register';
import './view/sw-order-detail-general/register';

/**
 * @sw-package checkout
 */

const { Application, Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-order-list', () => import('./page/sw-order-list'));
Shopware.Component.register('sw-order-detail', () => import('./page/sw-order-detail'));
Shopware.Component.register('sw-order-state-change-modal', () => import('./component/sw-order-state-change-modal'));

Shopware.Component.register('sw-order-document-card', () => import('./component/sw-order-document-card'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-order', {
    type: 'core',
    name: 'order',
    title: 'sw-order.general.mainMenuItemGeneral',
    description: 'sw-order.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-purple-default)',
    icon: 'regular-shopping-bag',
    favicon: 'icon-module-orders.svg',
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
                    return { orderId: $route.params.id.toLowerCase() };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-order',
            label: 'sw-order.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-purple-default)',
            icon: 'regular-shopping-bag',
            position: 30,
            privilege: 'order.viewer',
        },
        {
            path: 'sw.order.index',
            label: 'sw-order.general.mainMenuItemList',
            parent: 'sw-order',
            privilege: 'order.viewer',
        },
    ],

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

Application.addServiceProvider('documentV2Service', (container) => {
    return new DocumentV2Service(container.documentV2ApiService);
});
