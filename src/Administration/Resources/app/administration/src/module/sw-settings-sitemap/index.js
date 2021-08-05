import './page/sw-settings-sitemap';

const { Module } = Shopware;

Module.register('sw-settings-sitemap', {
    type: 'core',
    name: 'settings-sitemap',
    title: 'sw-settings-sitemap.general.mainMenuItemGeneral',
    description: 'sw-settings-sitemap.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-sitemap',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.sitemap.index',
        icon: 'default-location-map',
        privilege: 'system.system_config',
    },
});
