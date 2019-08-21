import './extension/sw-settings-index';
import './page/sw-settings-store';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-store', {
    type: 'core',
    name: 'settings-store',
    title: 'sw-settings-store.general.mainMenuItemGeneral',
    description: 'sw-settings-store.general.description',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-store',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
