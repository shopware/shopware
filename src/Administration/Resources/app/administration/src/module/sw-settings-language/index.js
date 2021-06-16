import './page/sw-settings-language-list';
import './page/sw-settings-language-detail';

import './acl';

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

    routes: {
        index: {
            component: 'sw-settings-language-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'language.viewer',
            },
        },
        detail: {
            component: 'sw-settings-language-detail',
            path: 'detail/:id?',
            meta: {
                parentPath: 'sw.settings.language.index',
                privilege: 'language.viewer',
            },
            props: {
                default: (route) => ({ languageId: route.params.id }),
            },
        },
        create: {
            component: 'sw-settings-language-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.language.index',
                privilege: 'language.creator',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.language.index',
        icon: 'default-location-flag',
        privilege: 'language.viewer',
    },
});
