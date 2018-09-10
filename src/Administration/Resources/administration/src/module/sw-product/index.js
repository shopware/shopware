import { Module } from 'src/core/shopware';
import './component/sw-product-basic-form';
import './component/sw-product-category-form';
import './component/sw-product-price-form';
import './component/sw-product-settings-form';
import './component/sw-product-media-form';
import './view/sw-product-detail-base';
import './view/sw-product-create-base';
import './view/sw-product-detail-context-prices';
import './page/sw-product-list';
import './page/sw-product-detail';
import './page/sw-product-create';

Module.register('sw-product', {
    type: 'core',
    name: 'Products',
    description: 'sw-product.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',

    routes: {
        index: {
            components: {
                default: 'sw-product-list',
                sidebar: 'sw-product-sidebar'
            },
            path: 'index'
        },

        create: {
            component: 'sw-product-create',
            path: 'create',
            redirect: {
                name: 'sw.product.create.base'
            },
            children: {
                base: {
                    component: 'sw-product-create-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.product.index'
                    }
                }
            }
        },

        detail: {
            component: 'sw-product-detail',
            path: 'detail/:id',
            redirect: {
                name: 'sw.product.detail.base'
            },
            children: {
                base: {
                    component: 'sw-product-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.product.index'
                    }
                },
                prices: {
                    component: 'sw-product-detail-context-prices',
                    path: 'prices',
                    meta: {
                        parentPath: 'sw.product.index'
                    }
                }
            }
        }
    },

    navigation: [{
        id: 'sw-product',
        label: 'sw-product.general.mainMenuItemGeneral',
        color: '#57D9A3',
        path: 'sw.product.index',
        icon: 'default-symbol-products',
        position: 20
    }, {
        path: 'sw.product.index',
        label: 'sw-product.general.mainMenuItemList',
        parent: 'sw-product'
    }, {
        path: 'sw.product.create',
        label: 'sw-product.general.mainMenuItemAdd',
        parent: 'sw-product',
        color: '#57D9A3'
    }]
});
