/**
 * @sw-package discovery
 */
import './acl';
import './page/sw-settings-delivery-time-detail/register';
import './page/sw-settings-delivery-time-list/register';

Shopware.Component.extend(
    'sw-settings-delivery-time-create',
    'sw-settings-delivery-time-detail',
    () => import('./page/sw-settings-delivery-time-create'),
);

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-delivery-time', {
    type: 'core',
    name: 'settings-delivery-time',
    title: 'sw-settings-delivery-time.general.mainMenuItemGeneral',
    description: 'sw-settings-delivery-time.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-clock',
    favicon: 'icon-module-settings.svg',
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
        group: 'commerce',
        to: 'sw.settings.delivery.time.index',
        icon: 'regular-clock',
        privilege: 'delivery_times.viewer',
    },
});
