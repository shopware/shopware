import './page/sw-settings-address';

const { Module } = Shopware;

Module.register('sw-settings-address', {
    type: 'core',
    name: 'settings-address',
    title: 'sw-settings-address.general.mainMenuItemGeneral',
    description: 'sw-settings-address.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
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
        icon: 'default-object-address',
        privilege: 'system.system_config',
    },
});
