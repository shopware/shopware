import './acl';
import defaultSearchConfiguration from './default-search-configuration';
import './component/sw-product-stream-field-select/register';
import './component/sw-product-stream-filter/register';
import './component/sw-product-stream-modal-preview/register';
import './component/sw-product-stream-value/register';
import './page/sw-product-stream-detail/register';
import './page/sw-product-stream-list/register';

const { Module } = Shopware;

/**
 * @sw-package inventory
 * @private
 */
Module.register('sw-product-stream', {
    type: 'core',
    name: 'product-stream',
    title: 'sw-product-stream.general.mainMenuItemGeneral',
    description: 'sw-product-stream.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-green-default)',
    icon: 'regular-layer-group',
    favicon: 'icon-module-products.svg',
    entity: 'product_stream',

    routes: {
        index: {
            components: {
                default: 'sw-product-stream-list',
            },
            path: 'index',
            meta: {
                privilege: 'product_stream.viewer',
            },
        },
        create: {
            component: 'sw-product-stream-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.product.stream.index',
                privilege: 'product_stream.viewer',
            },
        },
        detail: {
            component: 'sw-product-stream-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.product.stream.index',
                privilege: 'product_stream.viewer',
            },
            props: {
                default(route) {
                    return { productStreamId: route.params.id.toLowerCase() };
                },
            },
        },
    },

    navigation: [
        {
            path: 'sw.product.stream.index',
            label: 'sw-product-stream.general.mainMenuItemGeneral',
            id: 'sw-product-stream',
            privilege: 'product_stream.viewer',
            parent: 'sw-catalogue',
            color: 'var(--sw-color-module-green-default)',
            position: 30,
        },
    ],

    defaultSearchConfiguration,
});
