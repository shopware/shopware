import './extension/sw-settings-index';
import './page/sw-settings-listing';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-listing', {
    type: 'core',
    name: 'settings-listing',
    title: 'sw-settings-listing.general.mainMenuItemGeneral',
    description: 'sw-settings-listing.general.description',
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
            component: 'sw-settings-listing',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
