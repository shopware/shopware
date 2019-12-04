import './extension/sw-settings-index';
import './page/sw-settings-logging-list';

import './component/sw-settings-logging-entry-info';
import './component/sw-settings-logging-mail-sent-info';

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
                parentPath: 'sw.settings.index'
            }
        }
    }
});
