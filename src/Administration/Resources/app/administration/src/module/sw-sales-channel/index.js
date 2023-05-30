/**
 * @package sales-channel
 */

import './service/export-template.service';
import './product-export-templates';
import './service/domain-link.service';
import './service/sales-channel-favorites.service';
import './component/structure/sw-admin-menu-extension';
import './component/structure/sw-sales-channel-menu';
import './component/sw-sales-channel-products-assignment-single-products';
import './component/sw-sales-channel-product-assignment-categories';
import './component/sw-sales-channel-products-assignment-dynamic-product-groups';
import './acl';

import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-sales-channel-defaults-select', () => import('./component/sw-sales-channel-defaults-select'));
Shopware.Component.register('sw-sales-channel-modal', () => import('./component/sw-sales-channel-modal'));
Shopware.Component.register('sw-sales-channel-modal-grid', () => import('./component/sw-sales-channel-modal-grid'));
Shopware.Component.register('sw-sales-channel-modal-detail', () => import('./component/sw-sales-channel-modal-detail'));
Shopware.Component.register('sw-sales-channel-detail-domains', () => import('./component/sw-sales-channel-detail-domains'));
Shopware.Component.register('sw-sales-channel-detail-hreflang', () => import('./component/sw-sales-channel-detail-hreflang'));
Shopware.Component.register('sw-sales-channel-detail', () => import('./page/sw-sales-channel-detail'));
Shopware.Component.extend('sw-sales-channel-create', 'sw-sales-channel-detail', () => import('./page/sw-sales-channel-create'));
Shopware.Component.register('sw-sales-channel-list', () => import('./page/sw-sales-channel-list'));
Shopware.Component.register('sw-sales-channel-detail-base', () => import('./view/sw-sales-channel-detail-base'));
Shopware.Component.register('sw-sales-channel-detail-products', () => import('./view/sw-sales-channel-detail-products'));
Shopware.Component.register('sw-sales-channel-detail-analytics', () => import('./view/sw-sales-channel-detail-analytics'));
Shopware.Component.extend('sw-sales-channel-create-base', 'sw-sales-channel-detail-base', () => import('./view/sw-sales-channel-create-base'));
Shopware.Component.register('sw-sales-channel-detail-product-comparison', () => import('./view/sw-sales-channel-detail-product-comparison'));
Shopware.Component.register('sw-sales-channel-detail-product-comparison-preview', () => import('./view/sw-sales-channel-detail-product-comparison-preview'));
Shopware.Component.register('sw-sales-channel-products-assignment-modal', () => import('./component/sw-sales-channel-products-assignment-modal'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-sales-channel', {
    type: 'core',
    name: 'sales-channel',
    title: 'sw-sales-channel.general.titleMenuItems',
    description: 'The module for managing Sales Channels.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#14D7A5',
    icon: 'regular-server',
    entity: 'sales_channel',

    searchMatcher: (regex, labelType, manifest) => {
        const match = labelType.toLowerCase().match(regex);

        if (!match) {
            return false;
        }

        return [
            {
                name: manifest.name,
                icon: manifest.icon,
                color: manifest.color,
                label: labelType,
                entity: manifest.entity,
                route: manifest.routes.list,
                privilege: manifest.routes.list?.meta.privilege,
            },
        ];
    },

    routes: {
        detail: {
            component: 'sw-sales-channel-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.sales.channel.list',
                privilege: 'sales_channel.viewer',
            },
            redirect: {
                name: 'sw.sales.channel.detail.base',
            },
            children: {
                base: {
                    component: 'sw-sales-channel-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                products: {
                    component: 'sw-sales-channel-detail-products',
                    path: 'products',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                productComparison: {
                    component: 'sw-sales-channel-detail-product-comparison',
                    path: 'product-comparison',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                analytics: {
                    component: 'sw-sales-channel-detail-analytics',
                    path: 'analytics',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
            },
        },

        create: {
            component: 'sw-sales-channel-create',
            path: 'create/:typeId',
            redirect: {
                name: 'sw.sales.channel.create.base',
            },
            children: {
                base: {
                    component: 'sw-sales-channel-create-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.creator',
                    },
                },
            },
        },

        list: {
            component: 'sw-sales-channel-list',
            path: 'list',
            meta: {
                privilege: 'sales_channel.viewer',
            },
        },
    },

    defaultSearchConfiguration,
});
