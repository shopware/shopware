/**
 * @package system-settings
 */
import './acl';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.extend('sw-settings-custom-field-set-create', 'sw-settings-custom-field-set-detail', () => import('./page/sw-settings-custom-field-set-create'));
Shopware.Component.register('sw-settings-custom-field-set-list', () => import('./page/sw-settings-custom-field-set-list'));
Shopware.Component.register('sw-settings-custom-field-set-detail', () => import('./page/sw-settings-custom-field-set-detail'));
Shopware.Component.register('sw-custom-field-translated-labels', () => import('./component/sw-custom-field-translated-labels'));
Shopware.Component.register('sw-custom-field-set-detail-base', () => import('./component/sw-custom-field-set-detail-base'));
Shopware.Component.register('sw-custom-field-list', () => import('./component/sw-custom-field-list'));
Shopware.Component.register('sw-custom-field-detail', () => import('./component/sw-custom-field-detail'));
Shopware.Component.register('sw-custom-field-type-base', () => import('./component/sw-custom-field-type-base'));
Shopware.Component.extend('sw-custom-field-type-select', 'sw-custom-field-type-base', () => import('./component/sw-custom-field-type-select'));
Shopware.Component.extend('sw-custom-field-type-entity', 'sw-custom-field-type-select', () => import('./component/sw-custom-field-type-entity'));
Shopware.Component.extend('sw-custom-field-type-text', 'sw-custom-field-type-base', () => import('./component/sw-custom-field-type-text'));
Shopware.Component.extend('sw-custom-field-type-number', 'sw-custom-field-type-base', () => import('./component/sw-custom-field-type-number'));
Shopware.Component.extend('sw-custom-field-type-date', 'sw-custom-field-type-base', () => import('./component/sw-custom-field-type-date'));
Shopware.Component.extend('sw-custom-field-type-checkbox', 'sw-custom-field-type-base', () => import('./component/sw-custom-field-type-checkbox'));
Shopware.Component.extend('sw-custom-field-type-text-editor', 'sw-custom-field-type-base', () => import('./component/sw-custom-field-type-text-editor'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-custom-field', {
    type: 'core',
    name: 'settings-custom-field',
    title: 'sw-settings-custom-field.general.mainMenuItemGeneral',
    description: 'sw-settings-custom-field.general.description',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'custom-field-set',

    routes: {
        index: {
            component: 'sw-settings-custom-field-set-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'custom_field.viewer',
            },
        },
        detail: {
            component: 'sw-settings-custom-field-set-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.custom.field.index',
                privilege: 'custom_field.viewer',
            },
        },
        create: {
            component: 'sw-settings-custom-field-set-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.custom.field.index',
                privilege: 'custom_field.creator',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.custom.field.index',
        icon: 'regular-bars-square',
        privilege: 'custom_field.viewer',
    },
});
