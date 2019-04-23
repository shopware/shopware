import { Module } from 'src/core/shopware';
import './component/sw-product-basic-form';
import './component/sw-product-category-form';
import './component/sw-product-variants/sw-product-modal-variant-generation';
import './component/sw-product-variants/sw-product-modal-delivery';
import './component/sw-product-price-form';
import './component/sw-product-settings-form';
import './component/sw-product-media-form';
import './component/sw-product-visibility-select';
import './component/sw-product-visibility-detail';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-restriction-selection';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-selection';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-prices';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-restrictions';
import './component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-order';
import './component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-media';
import './component/sw-product-variants/sw-product-variants-overview';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-price-field';
import './view/sw-product-detail-base';
import './view/sw-product-create-base';
import './view/sw-product-detail-context-prices';
import './view/sw-product-detail-properties';
import './view/sw-product-detail-variants';
import './page/sw-product-list';
import './page/sw-product-detail';
import './page/sw-product-create';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-product', {
    type: 'core',
    name: 'sw-product.general.mainMenuItemGeneral',
    description: 'sw-product.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    favicon: 'icon-module-products.png',
    entity: 'product',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            components: {
                default: 'sw-product-list'
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
                },
                properties: {
                    component: 'sw-product-detail-properties',
                    path: 'properties',
                    meta: {
                        parentPath: 'sw.product.index'
                    }
                },
                variants: {
                    component: 'sw-product-detail-variants',
                    path: 'variants',
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
    }]
});
