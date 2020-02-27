import './page/sw-settings-cache-index';
import './component/sw-settings-cache-modal';

const { Module } = Shopware;

Module.register('sw-settings-cache', {
    type: 'core',
    name: 'settings-cache',
    title: 'sw-settings-cache.general.mainMenuItemGeneral',
    description: 'sw-settings-cache.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-cache-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.cache.index',
        icon: 'default-action-replace'
    }
});
