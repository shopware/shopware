import './acl';

/**
 * @package customer-order
 */

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-delivery-time-list', () => import('./page/sw-settings-delivery-time-list'));
Shopware.Component.register('sw-settings-delivery-time-detail', () => import('./page/sw-settings-delivery-time-detail'));
Shopware.Component.extend('sw-settings-delivery-time-create', 'sw-settings-delivery-time-detail', () => import('./page/sw-settings-delivery-time-create'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-delivery-time', {
    type: 'core',
    name: 'settings-delivery-time',
    title: 'sw-settings-delivery-time.general.mainMenuItemGeneral',
    description: 'sw-settings-delivery-time.general.description',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'delivery_time',

    routes: {
        index: {
            component: 'sw-settings-delivery-time-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'delivery_times.viewer',
            },
        },
        detail: {
            component: 'sw-settings-delivery-time-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.delivery.time.index',
                privilege: 'delivery_times.viewer',
            },
        },
        create: {
            component: 'sw-settings-delivery-time-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.delivery.time.index',
                privilege: 'delivery_times.creator',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.delivery.time.index',
        icon: 'regular-clock',
        privilege: 'delivery_times.viewer',
    },
});
