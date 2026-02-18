/**
 * @sw-package framework
 */
import './mixin/sw-settings-list.mixin';
import './acl';
import './override/sw-settings-index-override';

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-item', () => import('./component/sw-settings-item'));
Shopware.Component.register('sw-system-config', () => import('./component/sw-system-config'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings', {
    type: 'core',
    name: 'settings',
    title: 'sw-settings.general.mainMenuItemGeneral',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: () => import('./page/sw-settings-index/sw-settings-index.vue'),
            path: 'index',
            icon: 'regular-cog',
            redirect: {
                name: 'sw.settings.index.shop',
            },
            children: {
                shop: {
                    path: 'shop',
                    meta: {
                        parentPath: 'sw.settings.index',
                    },
                },
                system: {
                    path: 'system',
                    meta: {
                        parentPath: 'sw.settings.index',
                    },
                },
                plugins: {
                    path: 'plugins',
                    meta: {
                        parentPath: 'sw.settings.index',
                    },
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-settings',
            label: 'sw-settings.general.mainMenuItemGeneral',
            color: '#9AA8B5',
            icon: 'regular-cog',
            path: 'sw.settings.index',
            position: 80,
        },
    ],
});
