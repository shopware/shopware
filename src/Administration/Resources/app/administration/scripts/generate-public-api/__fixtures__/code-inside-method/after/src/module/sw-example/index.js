/**
 * @sw-package framework
 */

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-example-detail', () => import('./page/sw-example-detail'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-example', {
    type: 'core',
    name: 'example',
    title: 'sw-example.general.mainMenuItemGeneral',
    description: 'sw-example.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'regular-products',
    entity: 'sales_channel',

    routes: {
        detail: {
            component: 'sw-example-detail',
            path: 'detail/:id',
            meta: {
                privilege: 'sales_channel.viewer',
            },
        },
    },
});
