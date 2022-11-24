const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-my-apps-error-page', () => import('./component/sw-my-apps-error-page'));
Shopware.Component.register('sw-my-apps-page', () => import('./page/sw-my-apps-page'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

/**
 * @package merchant-services
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-my-apps', {
    type: 'core',
    name: 'sw-my-apps',
    title: 'sw-my-apps.general.mainMenuItemGeneral',
    description: 'sw-my-apps.general.moduleDescription',
    icon: 'regular-view-grid',
    color: '#9AA8B5',
    routePrefixPath: 'my-apps',

    routes: {
        index: {
            component: 'sw-my-apps-page',
            path: ':appName/:moduleName?',
            props: {
                default(route) {
                    const { appName, moduleName } = route.params;
                    return {
                        appName,
                        moduleName,
                    };
                },
            },
        },
    },

    navigation: [{
        id: 'sw-my-apps',
        label: 'sw-my-apps.general.mainMenuItemGeneral',
        icon: 'regular-view-grid',
        color: '#9AA8B5',
        position: 100,
    }],
});

