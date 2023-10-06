// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-privilege-error', () => import('./page/sw-privilege-error'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
