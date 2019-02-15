import { Module } from 'src/core/shopware';
import './page/sw-product-stream-list';
import './page/sw-product-stream-detail';
import './page/sw-product-stream-create';
import { NEXT739 } from 'src/flag/feature_next739';
import './component/sw-product-stream-filter';

Module.register('sw-product-stream', {
    type: 'core',
    flag: NEXT739,
    name: 'Product stream',
    description: 'sw-product-stream.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    entity: 'product_stream',

    routes: {
        index: {
            components: {
                default: 'sw-product-stream-list'
            },
            path: 'index'
        },
        create: {
            component: 'sw-product-stream-create',
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
            }
        }
    },

    navigation: [{
        path: 'sw.product.stream.index',
        label: 'sw-product-stream.general.mainMenuItemGeneral',
        id: 'sw-product-stream',
        parent: 'sw-product',
        color: '#57D9A3'
    }]
});
