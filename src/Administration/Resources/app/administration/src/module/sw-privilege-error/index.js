import './page/sw-privilege-error';

Shopware.Module.register('sw-privilege-error', {
    type: 'core',
    name: 'privilege',
    title: 'sw-privilege-error.general.mainMenuItemGeneral',
    description: 'sw-privilege-error.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',

    routes: {
        index: {
            components: {
                default: 'sw-privilege-error',
            },
            path: 'index',
        },
    },
});
