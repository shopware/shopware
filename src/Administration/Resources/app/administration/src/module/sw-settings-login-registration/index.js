// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-settings-login-registration', () => import('./page/sw-settings-login-registration'));

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-login-registration', {
    type: 'core',
    name: 'settings-login-registration',
    title: 'sw-settings-login-registration.general.mainMenuItemGeneral',
    description: 'sw-settings-login-registration.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
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
        icon: 'regular-sign-in',
        privilege: 'system.system_config',
    },
});
