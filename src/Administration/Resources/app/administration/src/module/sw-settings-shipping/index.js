/**
 * @sw-package checkout
 */
import './acl';
import './page/sw-settings-shipping-detail/store/register';
import './component/sw-settings-shipping-price-matrix/register';
import defaultSearchConfiguration from './default-search-configuration';
import './component/sw-settings-shipping-price-matrices/register';
import './component/sw-price-rule-modal/register';
import './component/sw-settings-shipping-tax-cost/register';
import './page/sw-settings-shipping-list/register';
import './page/sw-settings-shipping-detail/register';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-shipping', {
    type: 'core',
    name: 'settings-shipping',
    title: 'sw-settings-shipping.general.mainMenuItemGeneral',
    description: 'sw-settings-shipping.general.descriptionTextModule',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-truck',
    favicon: 'icon-module-settings.svg',
    entity: 'shipping_method',

    routes: {
        index: {
            component: 'sw-settings-shipping-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'shipping.viewer',
            },
        },
        detail: {
            component: 'sw-settings-shipping-detail',
            path: 'detail/:id?',
            meta: {
                parentPath: 'sw.settings.shipping.index',
                privilege: 'shipping.viewer',
            },
            props: {
                default: (route) => ({ shippingMethodId: route.params.id?.toLowerCase() }),
            },
        },
        create: {
            component: 'sw-settings-shipping-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.shipping.index',
                privilege: 'shipping.creator',
            },
        },
    },

    settingsItem: {
        group: 'commerce',
        to: 'sw.settings.shipping.index',
        icon: 'regular-truck',
        privilege: 'shipping.viewer',
    },

    defaultSearchConfiguration,
});
