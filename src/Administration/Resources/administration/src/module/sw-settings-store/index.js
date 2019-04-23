import { Module } from 'src/core/shopware';

import './extension/sw-settings-index';
import './page/sw-settings-store';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-store', {
    type: 'core',
    name: 'sw-settings-store.general.mainMenuItemGeneral',
    description: 'Store specific settings',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'language',

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
    },

    navigation: [{
        label: 'sw-settings-store.general.mainMenuItemGeneral',
        id: 'sw-settings-store',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.store.index',
        parent: 'sw-settings'
    }]
});
