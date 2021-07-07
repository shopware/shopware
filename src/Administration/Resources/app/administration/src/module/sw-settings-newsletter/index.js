import './page/sw-settings-newsletter';

const { Module } = Shopware;

Module.register('sw-settings-newsletter', {
    type: 'core',
    name: 'settings-newsletter',
    title: 'sw-settings-newsletter.general.mainMenuItemGeneral',
    description: 'sw-settings-newsletter.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-communication-inbox',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-newsletter',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.newsletter.index',
        icon: 'default-communication-inbox',
        privilege: 'system.system_config',
    },
});
