import './page/sw-settings-logging-list';

import './component/sw-settings-logging-entry-info';
import './component/sw-settings-logging-mail-sent-info';
import './acl';

const { Module } = Shopware;

Module.register('sw-settings-logging', {
    type: 'core',
    name: 'settings-logging',
    title: 'sw-settings-logging.general.mainMenuItemGeneral',
    description: 'Log viewer',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'log_entry',

    routes: {
        index: {
            component: 'sw-settings-logging-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.logging',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.logging.index',
        icon: 'default-device-server',
        privilege: 'system.logging',
    },
});
