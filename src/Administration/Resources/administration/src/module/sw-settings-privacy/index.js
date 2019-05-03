import { Module } from 'src/core/shopware';
import './extension/sw-settings-index';
import './page/sw-settings-privacy';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';


Module.register('sw-settings-privacy', {
    type: 'core',
    name: 'settings-privacy',
    title: 'sw-settings-privacy.general.mainMenuItemGeneral',
    description: 'sw-settings-privacy.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'store_settings',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-privacy',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
