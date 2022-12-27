// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-settings-store', () => import('./page/sw-settings-store'));

const { Module } = Shopware;

/**
 * @package merchant-services
 * @private
 */
Module.register('sw-settings-store', {
    type: 'core',
    name: 'settings-store',
    title: 'sw-settings-store.general.mainMenuItemGeneral',
    description: 'sw-settings-store.general.description',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-store',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.store.index',
        icon: 'regular-laptop',
        privilege: 'system.system_config',
    },
});
