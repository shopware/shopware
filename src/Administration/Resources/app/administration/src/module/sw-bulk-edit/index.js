import './page/sw-bulk-edit-product';
import './component/sw-bulk-edit-custom-fields';
import './component/sw-bulk-edit-change-type';
import './component/sw-bulk-edit-change-type-field-renderer';
import './component/sw-bulk-edit-form-field-renderer';
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
        },
    },
});
