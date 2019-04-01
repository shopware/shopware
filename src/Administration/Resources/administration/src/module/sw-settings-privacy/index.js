import { Module } from 'src/core/shopware';
import './extension/sw-settings-index';
import './page/sw-settings-privacy-index';

import { NEXT2539 } from '../../flag/feature_next2539';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';


Module.register('sw-settings-privacy', {
    type: 'core',
    flag: NEXT2539,
    name: 'Privacy settings',
    description: 'sw-settings-privacy.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    entity: 'store_settings',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-privacy-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },

    navigation: [{
        id: 'sw-settings-privacy',
        icon: 'default-action-settings',
        label: 'sw-settings-privacy.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        path: 'sw.settings.privacy.index',
        parent: 'sw-settings'
    }]
});
