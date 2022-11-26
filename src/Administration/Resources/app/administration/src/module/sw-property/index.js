/*
 * @package inventory
 */

import './acl';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-property-list', () => import('./page/sw-property-list'));
Shopware.Component.register('sw-property-detail', () => import('./page/sw-property-detail'));
Shopware.Component.extend('sw-property-create', 'sw-property-detail', () => import('./page/sw-property-create'));
Shopware.Component.register('sw-property-option-detail', () => import('./component/sw-property-option-detail'));
Shopware.Component.register('sw-property-detail-base', () => import('./component/sw-property-detail-base'));
Shopware.Component.register('sw-property-option-list', () => import('./component/sw-property-option-list'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-property', {
    type: 'core',
    name: 'property',
    title: 'sw-property.general.mainMenuItemGeneral',
    description: 'sw-property.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'regular-products',
    favicon: 'icon-module-products.png',
    entity: 'property_group',

    routes: {
        index: {
            components: {
                default: 'sw-property-list',
            },
            path: 'index',
            alias: '/',
            meta: {
                privilege: 'property.viewer',
            },
        },
        detail: {
            component: 'sw-property-detail',
            path: 'detail/:id',
            props: {
                default: (route) => {
                    return {
                        groupId: route.params.id,
                    };
                },
            },
            meta: {
                parentPath: 'sw.property.index',
                privilege: 'property.viewer',
            },
        },
        create: {
            component: 'sw-property-create',
            path: 'create',
            meta: {
                parentPath: 'sw.property.index',
                privilege: 'property.creator',
            },
        },
        option: {
            component: 'sw-property-option-detail',
            path: 'detail/:groupId/option/:optionId',
            meta: {
                parentPath: 'sw.property.detail',
                privilege: 'property.editor',
            },
        },
    },

    navigation: [{
        id: 'sw-property',
        label: 'sw-property.general.mainMenuItemGeneral',
        parent: 'sw-catalogue',
        path: 'sw.property.index',
        position: 40,
        privilege: 'property.viewer',
    }],

    defaultSearchConfiguration,
});
