/**
 * @package sales-channel
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-settings-sitemap', () => import('./page/sw-settings-sitemap'));

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-sitemap', {
    type: 'core',
    name: 'settings-sitemap',
    title: 'sw-settings-sitemap.general.mainMenuItemGeneral',
    description: 'sw-settings-sitemap.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
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
        icon: 'regular-map',
        privilege: 'system.system_config',
    },
});
