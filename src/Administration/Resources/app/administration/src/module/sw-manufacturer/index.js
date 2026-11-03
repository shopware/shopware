/*
 * @sw-package inventory
 */

import './acl';
import defaultSearchConfiguration from './default-search-configuration';
import './page/sw-manufacturer-detail/register';
import './page/sw-manufacturer-list/register';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-manufacturer', {
    type: 'core',
    name: 'manufacturer',
    title: 'sw-manufacturer.general.mainMenuItemGeneral',
    description: 'sw-manufacturer.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-green-default)',
    icon: 'regular-factory',
    favicon: 'icon-module-products.svg',
    entity: 'product_manufacturer',

    routes: {
        index: {
            components: {
                default: 'sw-manufacturer-list',
            },
            path: 'index',
            meta: {
                privilege: 'product_manufacturer.viewer',
            },
        },
        create: {
            component: 'sw-manufacturer-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.manufacturer.index',
                privilege: 'product_manufacturer.creator',
            },
        },
        detail: {
            component: 'sw-manufacturer-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.manufacturer.index',
                privilege: 'product_manufacturer.viewer',
            },
            props: {
                default(route) {
                    return {
                        manufacturerId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
    },

    navigation: [
        {
            path: 'sw.manufacturer.index',
            privilege: 'product_manufacturer.viewer',
            label: 'sw-manufacturer.general.mainMenuItemList',
            id: 'sw-manufacturer',
            parent: 'sw-catalogue',
            color: 'var(--sw-color-module-green-default)',
            position: 50,
        },
    ],

    defaultSearchConfiguration,
});
