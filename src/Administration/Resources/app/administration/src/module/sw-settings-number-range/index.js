/**
 * @package system-settings
 */
import './acl';

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-number-range-list', () => import('./page/sw-settings-number-range-list'));
Shopware.Component.register('sw-settings-number-range-detail', () => import('./page/sw-settings-number-range-detail'));
Shopware.Component.extend('sw-settings-number-range-create', 'sw-settings-number-range-detail', () => import('./page/sw-settings-number-range-create'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-number-range', {
    type: 'core',
    name: 'settings-number-range',
    title: 'sw-settings-number-range.general.mainMenuItemGeneral',
    description: 'Number Range section in the settings module',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'number_range',

    routes: {
        index: {
            component: 'sw-settings-number-range-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'number_ranges.viewer',
            },
        },
        detail: {
            component: 'sw-settings-number-range-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.number.range.index',
                privilege: 'number_ranges.viewer',
            },
        },
        create: {
            component: 'sw-settings-number-range-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.number.range.index',
                privilege: 'number_ranges.creator',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.number.range.index',
        icon: 'regular-file-signature',
        privilege: 'number_ranges.viewer',
    },
});
