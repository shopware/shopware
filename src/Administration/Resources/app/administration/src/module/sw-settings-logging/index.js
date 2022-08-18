import './page/sw-settings-logging-list';

import './component/sw-settings-logging-entry-info';
import './component/sw-settings-logging-mail-sent-info';
import './acl';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-logging', {
    type: 'core',
    name: 'settings-logging',
    title: 'sw-settings-logging.general.mainMenuItemGeneral',
    description: 'Log viewer',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'log_entry',

    routes: {
        index: {
            component: 'sw-settings-logging-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.logging',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.logging.index',
        icon: 'regular-server',
        privilege: 'system.logging',
    },
});
