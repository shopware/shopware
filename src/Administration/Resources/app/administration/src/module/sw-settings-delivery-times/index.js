import './page/sw-settings-delivery-time-list';
import './page/sw-settings-delivery-time-detail';
import './page/sw-settings-delivery-time-create';

import './acl';

const { Module } = Shopware;

Module.register('sw-settings-delivery-time', {
    type: 'core',
    name: 'settings-delivery-time',
    title: 'sw-settings-delivery-time.general.mainMenuItemGeneral',
    description: 'sw-settings-delivery-time.general.description',
    color: '#9AA8B5',
    icon: 'default-action-settings',
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
        icon: 'default-time-clock',
        privilege: 'delivery_times.viewer',
    },
});
