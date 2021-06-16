import './component/sw-my-apps-error-page';
import './page/sw-my-apps-page';

const { Module } = Shopware;

Module.register('sw-my-apps', {
    type: 'core',
    name: 'sw-my-apps',
    title: 'sw-my-apps.general.mainMenuItemGeneral',
    description: 'sw-my-apps.general.moduleDescription',
    icon: 'default-view-grid',
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
        icon: 'default-view-grid',
        color: '#9AA8B5',
        position: 100,
    }],
});

