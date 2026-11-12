/**
 * @sw-package framework
 */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-example-detail', () => import('./page/sw-example-detail'));

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
        detail: {
            component: 'sw-example-detail',
            path: 'detail/:id',
            meta: {
                privilege: 'example.viewer',
            },
            props: {
                default: (route) => ({ exampleId: route.params.id }),
            },
        },
    },
});
