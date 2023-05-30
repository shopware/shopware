import './init';
import './acl';
import defaultSearchConfiguration from './default-search-configuration';

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-plugin-box', () => import('./component/sw-plugin-box'));
Shopware.Component.register('sw-settings-payment-sorting-modal', () => import('./component/sw-settings-payment-sorting-modal'));
Shopware.Component.register('sw-payment-card', () => import('./component/sw-payment-card'));
Shopware.Component.register('sw-settings-payment-overview', () => import('./page/sw-settings-payment-overview'));
Shopware.Component.register('sw-settings-payment-detail', () => import('./page/sw-settings-payment-detail'));
Shopware.Component.extend('sw-settings-payment-create', 'sw-settings-payment-detail', () => import('./page/sw-settings-payment-create'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-payment', {
    type: 'core',
    name: 'settings-payment',
    title: 'sw-settings-payment.general.mainMenuItemGeneral',
    description: 'Payment section in the settings module',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'payment_method',

    routes: {
        overview: {
            component: 'sw-settings-payment-overview',
            path: 'overview',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'payment.viewer',
            },
        },
        detail: {
            component: 'sw-settings-payment-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.payment.overview',
                privilege: 'payment.viewer',
            },
        },
        create: {
            component: 'sw-settings-payment-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.payment.overview',
                privilege: 'payment.creator',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.payment.overview',
        icon: 'regular-credit-card',
        privilege: 'payment.viewer',
    },

    defaultSearchConfiguration,
});
