/**
 * @sw-package framework
 */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-example-list', () => import('./page/sw-example-list'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-example', {
    type: 'core',
    name: 'example',
    title: 'sw-example.general.mainMenuItemGeneral',
    description: 'sw-example.general.descriptionTextModule',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-cog',
    entity: 'example',

    routes: {
        index: {
            component: 'sw-example-list',
            path: 'index',
            meta: {
                privilege: 'example.viewer',
            },
        },
    },
});
