/**
 * @sw-package fundamentals@framework
 */
import './acl';
import './component/sw-settings-currency-country-modal/register';
import './component/sw-settings-price-rounding/register';
import './page/sw-settings-currency-detail/register';
import './page/sw-settings-currency-list/register';

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-currency', {
    type: 'core',
    name: 'settings-currency',
    title: 'sw-settings-currency.general.mainMenuItemGeneral',
    description: 'Currency section in the settings module',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-euro',
    favicon: 'icon-module-settings.svg',
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
                        currencyId: route.params.id.toLowerCase(),
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
        group: 'localization',
        to: 'sw.settings.currency.index',
        icon: 'regular-euro',
        privilege: 'currencies.viewer',
    },
});
