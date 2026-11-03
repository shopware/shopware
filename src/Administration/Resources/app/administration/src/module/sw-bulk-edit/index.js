/**
 * @sw-package framework
 */
import './init/services.init';
import './component/product/sw-bulk-edit-product-visibility/register';
import './component/product/sw-bulk-edit-product-media/register';
import './component/product/sw-bulk-edit-product-media-form/register';
import './component/sw-bulk-edit-change-type/register';
import './component/sw-bulk-edit-change-type-field-renderer/register';
import './component/sw-bulk-edit-custom-fields/register';
import './component/sw-bulk-edit-document-generation-failed-list/register';
import './component/sw-bulk-edit-form-field-renderer/register';
import './component/sw-bulk-edit-order/sw-bulk-edit-order-documents/register';
import './component/sw-bulk-edit-order/sw-bulk-edit-order-documents-delete-documents/register';
import './component/sw-bulk-edit-order/sw-bulk-edit-order-documents-download-documents/register';
import './component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-invoice/register';
import './component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-delivery-note/register';
import './component/sw-bulk-edit-save-modal/register';
import './component/sw-bulk-edit-save-modal-confirm/register';
import './component/sw-bulk-edit-save-modal-error/register';
import './component/sw-bulk-edit-save-modal-process/register';
import './component/sw-bulk-edit-save-modal-success/register';
import './page/sw-bulk-edit-customer/register';
import './page/sw-bulk-edit-order/register';
import './page/sw-bulk-edit-product/register';

Shopware.Component.extend(
    'sw-bulk-edit-order-documents-generate-cancellation-invoice',
    'sw-bulk-edit-order-documents-generate-invoice',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-cancellation-invoice'),
);
Shopware.Component.extend(
    'sw-bulk-edit-order-documents-generate-credit-note',
    'sw-bulk-edit-order-documents-generate-invoice',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-credit-note'),
);
Shopware.Component.extend(
    'sw-bulk-edit-product-description',
    'sw-text-editor',
    () => import('./component/product/sw-bulk-edit-product-description'),
);

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-bulk-edit', {
    type: 'core',
    name: 'bulk-edit',
    title: 'global.default.bulkEdit',
    description: 'sw-bulk-edit.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',

    routes: {
        product: {
            component: 'sw-bulk-edit-product',
            path: 'product/:parentId/:includesDigital',
            meta: {
                parentPath: 'sw.product.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.product.save.confirm',
                    },
                    children: {
                        confirm: {
                            component: 'sw-bulk-edit-save-modal-confirm',
                            path: 'confirm',
                        },
                        process: {
                            component: 'sw-bulk-edit-save-modal-process',
                            path: 'process',
                        },
                        success: {
                            component: 'sw-bulk-edit-save-modal-success',
                            path: 'success',
                        },
                        error: {
                            component: 'sw-bulk-edit-save-modal-error',
                            path: 'error',
                        },
                    },
                },
            },
        },
        order: {
            component: 'sw-bulk-edit-order',
            path: 'order/:excludeDelivery',
            meta: {
                parentPath: 'sw.order.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.order.save.confirm',
                    },
                    children: {
                        confirm: {
                            component: 'sw-bulk-edit-save-modal-confirm',
                            path: 'confirm',
                        },
                        process: {
                            component: 'sw-bulk-edit-save-modal-process',
                            path: 'process',
                        },
                        success: {
                            component: 'sw-bulk-edit-save-modal-success',
                            path: 'success',
                        },
                        error: {
                            component: 'sw-bulk-edit-save-modal-error',
                            path: 'error',
                        },
                    },
                },
            },
        },

        customer: {
            component: 'sw-bulk-edit-customer',
            path: 'customer',
            meta: {
                parentPath: 'sw.customer.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.customer.save.confirm',
                    },
                    children: {
                        confirm: {
                            component: 'sw-bulk-edit-save-modal-confirm',
                            path: 'confirm',
                        },
                        process: {
                            component: 'sw-bulk-edit-save-modal-process',
                            path: 'process',
                        },
                        success: {
                            component: 'sw-bulk-edit-save-modal-success',
                            path: 'success',
                        },
                        error: {
                            component: 'sw-bulk-edit-save-modal-error',
                            path: 'error',
                        },
                    },
                },
            },
        },
    },
});
