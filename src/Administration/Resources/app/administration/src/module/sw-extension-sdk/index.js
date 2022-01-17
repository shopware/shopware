import './page/sw-extension-sdk-module';

Shopware.Module.register('sw-extension-sdk', {
    type: 'core',
    name: 'sw-extension-sdk',
    title: 'sw-extension-sdk.general.mainMenuItemGeneral',
    description: 'sw-extension-sdk.general.moduleDescription',
    icon: 'default-view-grid',
    color: '#9AA8B5',
    routePrefixPath: 'extension',

    routes: {
        index: {
            component: 'sw-extension-sdk-module',
            path: ':id',
            props: {
                default(route) {
                    const { id } = route.params;
                    return {
                        id,
                    };
                },
            },
        },
    },

    navigation: [{
        id: 'sw-extension-sdk',
        label: 'sw-extension-sdk.general.mainMenuItemGeneral',
        icon: 'default-view-grid',
        color: '#9AA8B5',
        position: 110,
    }],
});

