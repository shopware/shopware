import './page/sw-settings-store';

const { Module } = Shopware;

Module.register('sw-settings-store', {
    type: 'core',
    name: 'settings-store',
    title: 'sw-settings-store.general.mainMenuItemGeneral',
    description: 'sw-settings-store.general.description',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-store',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.store.index',
        icon: 'default-device-laptop',
        privilege: 'system.system_config',
    },
});
