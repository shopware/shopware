import { Module } from 'src/core/shopware';
import { NEXT685 } from '../../flag/feature_next685';
import './extension/sw-settings-index';
import './page/sw-settings-login-registration';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Module.register('sw-settings-login-registration', {
    type: 'core',
    flag: NEXT685,
    name: 'Basic information',
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
    },

    navigation: [{
        id: 'sw-settings-login-registration',
        icon: 'default-action-settings',
        label: 'sw-settings-login-registration.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        path: 'sw.settings.login.registration.index',
        parent: 'sw-settings'
    }]
});
