import './extension/sw-settings-index';
import './page/sw-settings-login-registration';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-login-registration', {
    type: 'core',
    name: 'settings-login-registration',
    title: 'sw-settings-login-registration.general.mainMenuItemGeneral',
    description: 'sw-settings-login-registration.general.description',
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
            component: 'sw-settings-login-registration',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
