import { Module } from 'src/core/shopware';
import { NEXT685 } from '../../flag/feature_next685';
import './extension/sw-settings-index';
import './page/sw-settings-basic-information';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Module.register('sw-settings-basic-information', {
    type: 'core',
    flag: NEXT685,
    name: 'Basic information',
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
    },

    navigation: [{
        id: 'sw-settings-basic-information',
        icon: 'default-action-settings',
        label: 'sw-settings-basic-information.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        path: 'sw.settings.basic.information.index',
        parent: 'sw-settings'
    }]
});
