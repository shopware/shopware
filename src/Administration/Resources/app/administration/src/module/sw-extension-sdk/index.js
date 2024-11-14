import './page/sw-extension-sdk-module';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-extension-sdk', {
    type: 'core',
    name: 'sw-extension-sdk',
    title: 'sw-extension-sdk.general.mainMenuItemGeneral',
    description: 'sw-extension-sdk.general.moduleDescription',
    icon: 'regular-view-grid',
    color: '#9AA8B5',
    routePrefixPath: 'extension',

    routes: {
        index: {
            component: 'sw-extension-sdk-module',
            path: ':id/:back?',
            props: {
                default(route) {
                    const { id, back } = route.params;
                    return {
                        id,
                        back,
                    };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-extension-sdk',
            label: 'sw-extension-sdk.general.mainMenuItemGeneral',
            icon: 'regular-view-grid',
            color: '#9AA8B5',
            position: 110,
        },
    ],
});
