/**
 * @sw-package discovery
 */

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-sales-channel-detail', () => import('./page/sw-sales-channel-detail'));
Shopware.Component.register('sw-sales-channel-list', () => import('./page/sw-sales-channel-list'));
Shopware.Component.register(
    'sw-sales-channel-detail-agentic-files',
    () => import('./view/sw-sales-channel-detail-agentic-files'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

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

    routes: {
        detail: {
            component: 'sw-sales-channel-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.sales.channel.list',
                privilege: 'sales_channel.viewer',
            },
            redirect: {
                name: 'sw.sales.channel.detail.agenticFiles',
            },
            children: {
                agenticFiles: {
                    component: 'sw-sales-channel-detail-agentic-files',
                    path: 'agentic-files',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                agenticFile: {
                    component: 'sw-sales-channel-detail-agentic-files',
                    path: 'agentic-files/:fileName(.*)',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
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
});
