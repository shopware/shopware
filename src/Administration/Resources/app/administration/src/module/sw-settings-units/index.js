import './extension/sw-settings-index';
import './page/sw-settings-units';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

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

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-units',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
