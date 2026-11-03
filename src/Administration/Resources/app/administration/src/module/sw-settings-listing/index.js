import './component/sw-settings-listing-default-sales-channel/register';
import './component/sw-settings-listing-delete-modal/register';
import './component/sw-settings-listing-option-criteria-grid/register';
import './component/sw-settings-listing-option-general-info/register';
import './component/sw-settings-listing-visibility-detail/register';
import './page/sw-settings-listing/register';
import './page/sw-settings-listing-option-base/register';
import './page/sw-settings-listing-option-create/register';
/**
 * @sw-package inventory
 */
const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-listing', {
    type: 'core',
    name: 'settings-listing',
    title: 'sw-settings-listing.general.mainMenuItemGeneral',
    description: 'sw-settings-listing.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-products',
    favicon: 'icon-module-settings.svg',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-listing',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },

        edit: {
            component: 'sw-settings-listing-option-base',
            path: 'edit/:id',
            meta: {
                parentPath: 'sw.settings.listing.index',
                privilege: 'system.system_config',
            },
        },

        create: {
            component: 'sw-settings-listing-option-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.listing.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'commerce',
        to: 'sw.settings.listing.index',
        icon: 'regular-products',
        privilege: 'system.system_config',
    },
});
