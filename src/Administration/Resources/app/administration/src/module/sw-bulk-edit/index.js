import './page/sw-bulk-edit-product';
import './page/sw-bulk-edit-order';
import './component/sw-bulk-edit-order/sw-bulk-edit-order-documents';
import './component/sw-bulk-edit-custom-fields';
import './component/sw-bulk-edit-change-type';
import './component/sw-bulk-edit-change-type-field-renderer';
import './component/sw-bulk-edit-form-field-renderer';
import './component/sw-bulk-edit-save-modal';
import './component/sw-bulk-edit-save-modal-confirm';
import './component/sw-bulk-edit-save-modal-process';
import './component/sw-bulk-edit-save-modal-success';
import './component/sw-bulk-edit-save-modal-error';
import './init/services.init';

const { Module } = Shopware;

Module.register('sw-bulk-edit', {
    type: 'core',
    name: 'bulk-edit',
    title: 'sw-bulk-edit.general.mainMenuTitle',
    description: 'sw-bulk-edit.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    favicon: 'icon-module-products.png',
    flag: 'FEATURE_NEXT_6061',

    routes: {
        product: {
            component: 'sw-bulk-edit-product',
            path: 'product',
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
            path: 'order',
            meta: {
                parentPath: 'sw.order.index',
            },
        },
    },
});
