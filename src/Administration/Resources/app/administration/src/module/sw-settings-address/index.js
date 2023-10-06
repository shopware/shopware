// @deprecated tag:v6.6.0 - Whole module will be removed as no longer necessary since the introduction of address formatting

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-settings-address', () => import('./page/sw-settings-address'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-address', {
    type: 'core',
    name: 'settings-address',
    title: 'sw-settings-address.general.mainMenuItemGeneral',
    description: 'sw-settings-address.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-address',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.address.index',
        icon: 'regular-book-user',
        privilege: 'system.system_config',
    },
});
