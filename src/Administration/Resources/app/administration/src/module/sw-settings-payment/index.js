import './init';
import './acl';
import defaultSearchConfiguration from './default-search-configuration';
import './component/sw-payment-card/register';
import './component/sw-plugin-box/register';
import './component/sw-settings-payment-sorting-modal/register';
import './page/sw-settings-payment-detail/register';
import './page/sw-settings-payment-create/register';
import './page/sw-settings-payment-overview/register';

const { Module } = Shopware;

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-payment', {
    type: 'core',
    name: 'settings-payment',
    title: 'sw-settings-payment.general.mainMenuItemGeneral',
    description: 'Payment section in the settings module',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-credit-card',
    favicon: 'icon-module-settings.svg',
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
                privilege: 'payment.editor',
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
        group: 'commerce',
        to: 'sw.settings.payment.overview',
        icon: 'regular-credit-card',
        privilege: 'payment.viewer',
    },

    defaultSearchConfiguration,
});
