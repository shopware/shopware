/**
 * @sw-package discovery
 */

import './service/export-template.service';
import './product-export-templates';
import './agentic-product-export-templates';
import './service/domain-link.service';
import './service/sales-channel-file.api.service';
import './service/sales-channel-product-assignment.api.service';
import './service/sales-channel-favorites.service';
import './component/structure/sw-admin-menu-extension';
import './acl';

import defaultSearchConfiguration from './default-search-configuration';
import './component/sw-agentic-commerce-tracking-config/register';
import './view/sw-sales-channel-detail-agentic-file/register';
import './view/sw-sales-channel-detail-product-export-insights/register';
import './component/structure/sw-sales-channel-menu/register';
import './component/sw-sales-channel-defaults-select/register';
import './component/sw-sales-channel-detail-domains/register';
import './component/sw-sales-channel-detail-hreflang/register';
import './component/sw-sales-channel-measurement/register';
import './component/sw-sales-channel-modal/register';
import './component/sw-sales-channel-modal-detail/register';
import './component/sw-sales-channel-modal-grid/register';
import './component/sw-sales-channel-product-assignment-categories/register';
import './component/sw-sales-channel-products-assignment-dynamic-product-groups/register';
import './component/sw-sales-channel-products-assignment-modal/register';
import './component/sw-sales-channel-products-assignment-single-products/register';
import './page/sw-sales-channel-detail/register';
import './page/sw-sales-channel-create/register';
import './page/sw-sales-channel-list/register';
import './view/sw-sales-channel-detail-base/register';
import './view/sw-sales-channel-create-base/register';
import './view/sw-sales-channel-detail-agentic-commerce-integration/register';
import './view/sw-sales-channel-detail-agentic-files/register';
import './view/sw-sales-channel-detail-analytics/register';
import './view/sw-sales-channel-detail-product-comparison/register';
import './view/sw-sales-channel-detail-product-comparison-preview/register';
import './view/sw-sales-channel-detail-products/register';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-sales-channel', {
    type: 'core',
    name: 'sales-channel',
    title: 'sw-sales-channel.general.titleMenuItems',
    description: 'The module for managing Sales Channels.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-storefront',
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
                agenticFiles: {
                    component: 'sw-sales-channel-detail-agentic-files',
                    path: 'agentic-files',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                agenticFile: {
                    component: 'sw-sales-channel-detail-agentic-file',
                    path: 'agentic-files/:fileName(.*)',
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
                agenticCommerceIntegration: {
                    component: 'sw-sales-channel-detail-agentic-commerce-integration',
                    path: 'agentic-commerce-integration',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                productExportInsights: {
                    component: 'sw-sales-channel-detail-product-export-insights',
                    path: 'product-export-insights',
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
