import { Module } from 'src/core/shopware';

import './extension/sw-settings-index';
import './page/sw-settings-language-list';
import './page/sw-settings-language-detail';
import './page/sw-settings-language-create';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-language', {
    type: 'core',
    name: 'sw-settings-language.general.mainMenuItemGeneral',
    description: 'Language section in the settings module',
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
            component: 'sw-settings-language-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-language-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.language.index'
            }
        },
        create: {
            component: 'sw-settings-language-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.language.index'
            }
        }
    },

    navigation: [{
        label: 'sw-settings-language.general.mainMenuItemGeneral',
        id: 'sw-settings-language',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.language.index',
        parent: 'sw-settings'
    }]
});
