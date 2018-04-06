import { Module } from 'src/core/shopware';
import './component/sw-product-basic-form';
import './component/sw-product-category-form';
import './component/sw-product-price-form';
import './component/sw-product-settings-form';
import './view/sw-product-detail-base';
import './view/sw-product-detail-context-prices';
import './page/sw-product-list';
import './page/sw-product-detail';
import './page/sw-product-create';

Module.register('sw-product', {
    type: 'core',
    name: 'Products',
    description: 'The module for managing products.',
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
            path: 'index/:offset?/:limit?/:sortBy?/:sortDirection?/:term?/:filters?'
        },

        create: {
            component: 'sw-product-create',
            path: 'create',
            redirect: {
                name: 'sw.product.create.base'
            },
            children: {
                base: {
                    component: 'sw-product-detail-base',
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
        path: 'sw.product.index',
        label: 'Produkte',
        color: '#57D9A3',
        icon: 'default-symbol-products'
    }, {
        path: 'sw.product.create',
        label: 'Produkt anlegen',
        parent: 'sw.product.index',
        color: '#57D9A3'
    }],

    commands: [{
        title: 'Übersicht',
        route: 'product.index'
    }, {
        title: '%0 öffnen',
        route: 'product.detail'
    }],

    shortcuts: {
        index: {
            mac: {
                title: 'product.index.shortcut.mac',
                combination: [
                    'CMD',
                    'P'
                ]
            },
            win: {
                title: 'product.index.shortcut.win',
                combination: [
                    'CTRL',
                    'P'
                ]
            }
        }
    }
});
