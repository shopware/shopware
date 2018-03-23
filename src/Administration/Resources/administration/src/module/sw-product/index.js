import { Module } from 'src/core/shopware';
import './src';

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
            path: 'index'
        },

        indexPaginated: {
            components: {
                default: 'sw-product-list',
                sidebar: 'sw-product-sidebar'
            },
            path: 'index/:offset/:limit/:sortBy/:sortDirection/:term?/:filters?'
        },

        create: {
            component: 'sw-product-detail',
            path: 'product/create',
            meta: {
                parentPath: 'sw.product.index'
            },
            children: {
                general: {
                    component: 'sw-product-detail-general',
                    path: 'general'
                }
            }
        },

        detail: {
            component: 'sw-product-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.product.index'
            },
            children: {
                general: {
                    component: 'sw-product-detail-general',
                    path: 'general'
                },
                variants: {
                    component: 'sw-product-detail-variants',
                    path: 'variants'
                },
                advancedPrices: {
                    component: 'sw-product-detail-advanced-prices',
                    path: 'advanced-prices'
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
