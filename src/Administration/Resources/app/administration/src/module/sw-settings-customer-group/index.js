import './acl';
import defaultSearchConfiguration from './default-search-configuration';

/**
 * @package customer-order
 */

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-customer-group-list', () => import('./page/sw-settings-customer-group-list'));
Shopware.Component.register('sw-settings-customer-group-detail', () => import('./page/sw-settings-customer-group-detail'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-customer-group', {
    type: 'core',
    name: 'settings-customer-group',
    title: 'sw-settings-customer-group.general.mainMenuItemGeneral',
    description: 'sw-settings-customer-group.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'customer_group',

    routes: {
        index: {
            component: 'sw-settings-customer-group-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'customer_groups.viewer',
            },
        },
        detail: {
            component: 'sw-settings-customer-group-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.customer.group.index',
                privilege: 'customer_groups.viewer',
            },
            props: {
                default(route) {
                    return {
                        customerGroupId: route.params.id,
                    };
                },
            },
        },
        create: {
            component: 'sw-settings-customer-group-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.customer.group.index',
                privilege: 'customer_groups.creator',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.customer.group.index',
        icon: 'regular-users',
        privilege: 'customer_groups.viewer',
    },

    defaultSearchConfiguration,
});
