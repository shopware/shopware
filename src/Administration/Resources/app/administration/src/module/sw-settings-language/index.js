/**
 * @sw-package fundamentals@discovery
 */
import './acl';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-language-list', () => import('./page/sw-settings-language-list'));
Shopware.Component.register('sw-settings-language-detail', () => import('./page/sw-settings-language-detail'));
Shopware.Component.register('sw-settings-language-add-modal', () => import('./component/sw-settings-language-add-modal'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-language', {
    type: 'core',
    name: 'settings-language',
    title: 'sw-settings-language.general.mainMenuItemGeneral',
    description: 'Language section in the settings module',
    color: '#848A96',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.svg',
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
                default: (route) => ({ languageId: route.params.id?.toLowerCase() }),
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
        group: 'localization',
        to: 'sw.settings.language.index',
        icon: 'regular-flag',
        privilege: 'language.viewer',
    },
});
