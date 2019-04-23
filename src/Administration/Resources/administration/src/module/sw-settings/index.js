import { Module } from 'src/core/shopware';
import './component/sw-settings-item';
import './component/sw-system-config';
import './page/sw-settings-index';
import './mixin/sw-settings-list.mixin';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings', {
    type: 'core',
    name: 'sw-settings.general.mainMenuItemGeneral',
    description: 'Settings Module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-index',
            path: 'index',
            icon: 'default-action-settings'
        }
    },

    navigation: [{
        id: 'sw-settings',
        label: 'sw-settings.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.index',
        position: 70
    }]
});
