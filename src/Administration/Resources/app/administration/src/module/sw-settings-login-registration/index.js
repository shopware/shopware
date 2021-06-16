import './page/sw-settings-login-registration';

const { Module } = Shopware;

Module.register('sw-settings-login-registration', {
    type: 'core',
    name: 'settings-login-registration',
    title: 'sw-settings-login-registration.general.mainMenuItemGeneral',
    description: 'sw-settings-login-registration.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-login-registration',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.login.registration.index',
        icon: 'default-action-log-in',
        privilege: 'system.system_config',
    },
});
