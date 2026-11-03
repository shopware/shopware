import './acl';
import defaultSearchConfiguration from './default-search-configuration';
import './component/sw-customer-address-form/register';
import './component/sw-customer-address-form-options/register';
import './component/sw-customer-base-form/register';
import './component/sw-customer-base-info/register';
import './component/sw-customer-card/register';
import './component/sw-customer-convert-guest-modal/register';
import './component/sw-customer-default-addresses/register';
import './component/sw-customer-imitate-customer-modal/register';
import './page/sw-customer-create/register';
import './page/sw-customer-detail/register';
import './page/sw-customer-list/register';
import './view/sw-customer-detail-addresses/register';
import './view/sw-customer-detail-base/register';
import './view/sw-customer-detail-order/register';

/**
 * @sw-package checkout
 */

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-customer', {
    type: 'core',
    name: 'customers',
    title: 'sw-customer.general.mainMenuItemGeneral',
    description: 'sw-customer.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-orange-default)',
    icon: 'regular-users',
    favicon: 'icon-module-customers.svg',
    entity: 'customer',

    routes: {
        index: {
            components: {
                default: 'sw-customer-list',
            },
            path: 'index',
            meta: {
                privilege: 'customer.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        create: {
            component: 'sw-customer-create',
            path: 'create',
            meta: {
                parentPath: 'sw.customer.index',
                privilege: 'customer.creator',
            },
        },

        detail: {
            component: 'sw-customer-detail',
            path: 'detail/:id',
            redirect: {
                name: 'sw.customer.detail.base',
            },
            children: {
                base: {
                    component: 'sw-customer-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.customer.index',
                        privilege: 'customer.viewer',
                    },
                },
                addresses: {
                    component: 'sw-customer-detail-addresses',
                    path: 'addresses',
                    meta: {
                        parentPath: 'sw.customer.index',
                        privilege: 'customer.viewer',
                    },
                },
                order: {
                    component: 'sw-customer-detail-order',
                    path: 'order',
                    meta: {
                        parentPath: 'sw.customer.index',
                        privilege: 'customer.viewer',
                    },
                },
            },
            meta: {
                privilege: 'customer.viewer',
                appSystem: {
                    view: 'detail',
                },
            },

            props: {
                default(route) {
                    return {
                        customerId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-customer',
            label: 'sw-customer.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-orange-default)',
            icon: 'regular-users',
            position: 40,
            privilege: 'customer.viewer',
        },
        {
            path: 'sw.customer.index',
            label: 'sw-customer.general.mainMenuItemList',
            color: 'var(--sw-color-module-orange-default)',
            icon: 'regular-users',
            parent: 'sw-customer',
            privilege: 'customer.viewer',
        },
    ],

    defaultSearchConfiguration,
});
