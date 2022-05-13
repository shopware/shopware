import './acl';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-currency-list', () => import('./page/sw-settings-currency-list'));
Shopware.Component.register('sw-settings-currency-detail', () => import('./page/sw-settings-currency-detail'));
Shopware.Component.register('sw-settings-price-rounding', () => import('./component/sw-settings-price-rounding'));
Shopware.Component.register('sw-settings-currency-country-modal', () => import('./component/sw-settings-currency-country-modal'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-currency', {
    type: 'core',
    name: 'settings-currency',
    title: 'sw-settings-currency.general.mainMenuItemGeneral',
    description: 'Currency section in the settings module',
    color: '#9AA8B5',
    icon: 'regular-cog',
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
        icon: 'regular-euro',
        privilege: 'currencies.viewer',
    },
});
