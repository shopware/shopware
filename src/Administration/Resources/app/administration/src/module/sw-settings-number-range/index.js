/**
 * @sw-package inventory
 */
import './acl';
import './page/sw-settings-number-range-detail/register';
import './page/sw-settings-number-range-create/register';
import './page/sw-settings-number-range-list/register';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-number-range', {
    type: 'core',
    name: 'settings-number-range',
    title: 'sw-settings-number-range.general.mainMenuItemGeneral',
    description: 'Number Range section in the settings module',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-file-signature',
    favicon: 'icon-module-settings.svg',
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
        group: 'general',
        to: 'sw.settings.number.range.index',
        icon: 'regular-file-signature',
        privilege: 'number_ranges.viewer',
    },
});
