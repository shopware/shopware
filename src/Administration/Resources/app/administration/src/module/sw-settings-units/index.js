import './page/sw-settings-units';
import './acl';

const { Module } = Shopware;

Module.register('sw-settings-units', {
    type: 'core',
    name: 'settings-units',
    title: 'sw-settings-units.general.mainMenuItemGeneral',
    description: 'Units section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'units',

    routes: {
        index: {
            component: 'sw-settings-units',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'scale_unit.viewer',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.units.index',
        icon: 'default-object-scale',
        privilege: 'scale_unit.viewer',
    },
});
