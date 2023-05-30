/**
 * @package system-settings
 */
import './acl';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-snippet-set-list', () => import('./page/sw-settings-snippet-set-list'));
Shopware.Component.register('sw-settings-snippet-list', () => import('./page/sw-settings-snippet-list'));
Shopware.Component.register('sw-settings-snippet-detail', () => import('./page/sw-settings-snippet-detail'));
Shopware.Component.extend('sw-settings-snippet-create', 'sw-settings-snippet-detail', () => import('./page/sw-settings-snippet-create'));
Shopware.Component.register('sw-settings-snippet-sidebar', () => import('./component/sidebar/sw-settings-snippet-sidebar'));
Shopware.Component.register('sw-settings-snippet-filter-switch', () => import('./component/sidebar/sw-settings-snippet-filter-switch'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-snippet', {
    type: 'core',
    name: 'settings-snippet',
    title: 'sw-settings-snippet.general.mainMenuItemGeneral',
    description: 'sw-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'snippet',

    routes: {
        index: {
            component: 'sw-settings-snippet-set-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'snippet.viewer',
            },
        },
        list: {
            component: 'sw-settings-snippet-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.snippet.index',
                privilege: 'snippet.viewer',
            },
        },
        detail: {
            component: 'sw-settings-snippet-detail',
            path: 'detail/:key',
            meta: {
                parentPath: 'sw.settings.snippet.list',
                privilege: 'snippet.viewer',
            },
        },
        create: {
            component: 'sw-settings-snippet-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.snippet.list',
                privilege: 'snippet.viewer',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.snippet.index',
        icon: 'regular-globe-stand',
        privilege: 'snippet.viewer',
    },
});
