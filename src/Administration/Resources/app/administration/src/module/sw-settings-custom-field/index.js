/**
 * @sw-package framework
 */
import './acl';
import './component/sw-custom-field-detail/register';
import './component/sw-custom-field-list/register';
import './component/sw-custom-field-set-detail-base/register';
import './component/sw-custom-field-translated-labels/register';
import './component/sw-custom-field-type-base/register';
import './component/sw-custom-field-type-date/register';
import './component/sw-custom-field-type-select/register';
import './component/sw-custom-field-type-entity/register';
import './component/sw-custom-field-type-number/register';
import './page/sw-settings-custom-field-set-detail/register';
import './page/sw-settings-custom-field-set-create/register';

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-custom-field-set-list', () => import('./page/sw-settings-custom-field-set-list'));
Shopware.Component.extend(
    'sw-custom-field-type-text',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-text'),
);
Shopware.Component.extend(
    'sw-custom-field-type-checkbox',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-checkbox'),
);
Shopware.Component.extend(
    'sw-custom-field-type-text-editor',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-text-editor'),
);
Shopware.Component.extend(
    'sw-custom-field-type-colorpicker',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-colorpicker'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-custom-field', {
    type: 'core',
    name: 'settings-custom-field',
    title: 'sw-settings-custom-field.general.mainMenuItemGeneral',
    description: 'sw-settings-custom-field.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-bars-square',
    favicon: 'icon-module-settings.svg',
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
        group: 'content',
        to: 'sw.settings.custom.field.index',
        icon: 'regular-bars-square',
        privilege: 'custom_field.viewer',
    },
});
