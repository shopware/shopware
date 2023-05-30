const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-settings-cart', () => import('./page/sw-settings-cart'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-cart', {
    type: 'core',
    name: 'settings-cart',
    title: 'sw-settings-cart.general.mainMenuItemGeneral',
    description: 'sw-settings-cart.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-cart',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.cart.index',
        icon: 'regular-shopping-cart',
        privilege: 'system.system_config',
    },
});
