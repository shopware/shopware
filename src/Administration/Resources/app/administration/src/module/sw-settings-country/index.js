/**
 * @package system-settings
 */
import './component/sw-settings-country-address-handling';
import './component/sw-settings-country-new-snippet-modal';
import './component/sw-multi-snippet-drag-and-drop';
import './component/sw-settings-country-preview-template';

import './acl';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-country-list', () => import('./page/sw-settings-country-list'));
Shopware.Component.register('sw-settings-country-detail', () => import('./page/sw-settings-country-detail'));
Shopware.Component.extend('sw-settings-country-create', 'sw-settings-country-detail', () => import('./page/sw-settings-country-create'));
Shopware.Component.register('sw-country-state-detail', () => import('./component/sw-country-state-detail'));
Shopware.Component.register('sw-settings-country-general', () => import('./component/sw-settings-country-general'));
Shopware.Component.register('sw-settings-country-state', () => import('./component/sw-settings-country-state'));
Shopware.Component.register('sw-settings-country-currency-dependent-modal', () => import('./component/sw-settings-country-currency-dependent-modal'));
Shopware.Component.register('sw-settings-country-currency-hamburger-menu', () => import('./component/sw-settings-country-currency-hamburger-menu'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-country', {
    type: 'core',
    name: 'settings-country',
    title: 'sw-settings-country.general.mainMenuItemGeneral',
    description: 'Country section in the settings module',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'country',

    routes: {
        index: {
            component: 'sw-settings-country-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'country.viewer',
            },
        },
        detail: {
            component: 'sw-settings-country-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.country.index',
                privileges: ['country.viewer', 'country.editor'],
            },

            redirect: {
                name: 'sw.settings.country.detail.general',
            },

            children: {
                general: {
                    component: 'sw-settings-country-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privileges: ['country.editor', 'country.creator'],
                    },
                },

                state: {
                    component: 'sw-settings-country-state',
                    path: 'state',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privileges: ['country.editor', 'country.creator'],
                    },
                },

                'address-handling': {
                    component: 'sw-settings-country-address-handling',
                    path: 'address-handling',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privileges: ['country.editor', 'country.creator'],
                    },
                },
            },
        },
        create: {
            component: 'sw-settings-country-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.country.index',
                privilege: 'country.creator',
            },

            redirect: {
                name: 'sw.settings.country.create.general',
            },

            children: {
                general: {
                    component: 'sw-settings-country-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privilege: 'country.creator',
                    },
                },

                state: {
                    component: 'sw-settings-country-state',
                    path: 'state',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privilege: 'country.creator',
                    },
                },

                'address-handling': {
                    component: 'sw-settings-country-address-handling',
                    path: 'address-handling',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privileges: 'country.creator',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.country.index',
        icon: 'regular-map',
        privilege: 'country.viewer',
    },
});
