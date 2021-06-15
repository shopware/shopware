import './page/sw-settings-currency-list';
import './page/sw-settings-currency-detail';
import './component/sw-settings-price-rounding';
import './component/sw-settings-currency-country-modal';

import './acl';

const { Module } = Shopware;

Module.register('sw-settings-currency', {
    type: 'core',
    name: 'settings-currency',
    title: 'sw-settings-currency.general.mainMenuItemGeneral',
    description: 'Currency section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'currency',

    routes: {
        index: {
            component: 'sw-settings-currency-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'currencies.viewer',
            },
        },
        detail: {
            component: 'sw-settings-currency-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.currency.index',
                privilege: 'currencies.viewer',
            },
            props: {
                default(route) {
                    return {
                        currencyId: route.params.id,
                    };
                },
            },
        },
        create: {
            component: 'sw-settings-currency-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.currency.index',
                privilege: 'currencies.creator',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.currency.index',
        icon: 'default-symbol-euro',
        privilege: 'currencies.viewer',
    },
});
