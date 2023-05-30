const { Module } = Shopware;

/**
 * @package merchant-services
 * @private
 */
Shopware.Component.register('sw-settings-usage-data', () => import('./page/sw-settings-usage-data'));

/**
 * @package merchant-services
 * @private
 */
Module.register('sw-settings-usage-data', {
    type: 'core',
    name: 'usage-data',
    title: 'sw-settings-usage-data.general.mainMenuItemGeneral',
    description: 'sw-settings-usage-data.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-usage-data',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.usage.data.index',
        icon: 'regular-analytics',
        privilege: 'system.system_config',
    },
});

/**
 * @package merchant-services
 * @private
 */
export {};
