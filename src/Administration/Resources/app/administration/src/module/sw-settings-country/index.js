/**
 * @sw-package fundamentals@discovery
 */

import './acl';
import './component/sw-country-state-detail/register';
import './component/sw-multi-snippet-drag-and-drop/register';
import './component/sw-settings-country-address-handling/register';
import './component/sw-settings-country-currency-dependent-modal/register';
import './component/sw-settings-country-currency-hamburger-menu/register';
import './component/sw-settings-country-general/register';
import './component/sw-settings-country-new-snippet-modal/register';
import './component/sw-settings-country-preview-template/register';
import './component/sw-settings-country-state/register';
import './page/sw-settings-country-detail/register';
import './page/sw-settings-country-create/register';
import './page/sw-settings-country-list/register';

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-country', {
    type: 'core',
    name: 'settings-country',
    title: 'sw-settings-country.general.mainMenuItemGeneral',
    description: 'Country section in the settings module',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-map',
    favicon: 'icon-module-settings.svg',
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
        group: 'localization',
        to: 'sw.settings.country.index',
        icon: 'regular-map',
        privilege: 'country.viewer',
    },
});
