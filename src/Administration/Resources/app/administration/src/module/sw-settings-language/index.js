import './extension/sw-settings-index';
import './page/sw-settings-language-list';
import './page/sw-settings-language-detail';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-language', {
    type: 'core',
    name: 'settings-language',
    title: 'sw-settings-language.general.mainMenuItemGeneral',
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
            path: 'detail/:id?',
            meta: {
                parentPath: 'sw.settings.language.index'
            },
            props: {
                default: (route) => ({ languageId: route.params.id })
            }
        },
        create: {
            component: 'sw-settings-language-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.language.index'
            }
        }
    }
});
