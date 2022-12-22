import './page/sw-settings-shipping-list';
import './page/sw-settings-shipping-detail';
import './component/sw-price-rule-modal';
import './component/sw-settings-shipping-price-matrices';
import './component/sw-settings-shipping-price-matrix';
import './component/sw-settings-shipping-tax-cost';
import './acl';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Shopware;

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-shipping', {
    type: 'core',
    name: 'settings-shipping',
    title: 'sw-settings-shipping.general.mainMenuItemGeneral',
    description: 'sw-settings-shipping.general.descriptionTextModule',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
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
                default: (route) => ({ shippingMethodId: route.params.id }),
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
        group: 'shop',
        to: 'sw.settings.shipping.index',
        icon: 'default-package-open',
        privilege: 'shipping.viewer',
    },

    defaultSearchConfiguration,
});
