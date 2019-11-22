import './page/sw-product-stream-list';
import './page/sw-product-stream-detail';
import './component/sw-product-stream-field-select';
import './component/sw-product-stream-value';
import './component/sw-product-stream-modal-preview';
import './component/sw-product-stream-filter';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-product-stream', {
    type: 'core',
    name: 'product-stream',
    title: 'sw-product-stream.general.mainMenuItemGeneral',
    description: 'sw-product-stream.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    favicon: 'icon-module-products.png',
    entity: 'product_stream',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            components: {
                default: 'sw-product-stream-list'
            },
            path: 'index'
        },
        create: {
            component: 'sw-product-stream-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.product.stream.index'
            }
        },
        detail: {
            component: 'sw-product-stream-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.product.stream.index'
            },
            props: {
                default(route) {
                    return { productStreamId: route.params.id };
                }
            }
        }
    },

    navigation: [{
        path: 'sw.product.stream.index',
        label: 'sw-product-stream.general.mainMenuItemGeneral',
        id: 'sw-product-stream',
        parent: 'sw-catalogue',
        color: '#57D9A3',
        position: 30
    }]
});
