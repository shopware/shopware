import './extension/sw-settings-index';
import './page/sw-settings-basic-information';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-basic-information', {
    type: 'core',
    name: 'settings-basic-information',
    title: 'sw-settings-basic-information.general.mainMenuItemGeneral',
    description: 'sw-settings-basic-information.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-basic-information',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
