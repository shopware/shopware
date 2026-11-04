/**
 * @sw-package checkout
 */
import './init/services.init';
import './acl';

import './page/sw-promotion-v2-detail/store/register';
import defaultSearchConfiguration from './default-search-configuration';
import './component/discount/sw-promotion-v2-settings-discount-type/register';
import './component/discount/sw-promotion-v2-settings-rule-selection/register';
import './component/discount/sw-promotion-v2-settings-trigger/register';
import './component/discount/sw-promotion-v2-wizard-description/register';
import './component/discount/sw-promotion-v2-wizard-discount-selection/register';
import './component/promotion-codes/sw-promotion-v2-generate-codes-modal/register';
import './component/promotion-codes/sw-promotion-v2-individual-codes-behavior/register';
import './component/sw-promotion-discount-component/register';
import './component/sw-promotion-v2-cart-condition-form/register';
import './component/sw-promotion-v2-empty-state-hero/register';
import './component/sw-promotion-v2-sales-channel-select/register';
import './page/sw-promotion-v2-list/register';
import './view/sw-promotion-detail-discounts/register';
import './view/sw-promotion-v2-conditions/register';
import './view/sw-promotion-v2-detail-base/register';

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-promotion-v2-detail', () => import('./page/sw-promotion-v2-detail'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-promotion-v2', {
    type: 'core',
    name: 'promotion-v2',
    title: 'sw-promotion-v2.general.mainMenuItemGeneral',
    description: 'sw-promotion-v2.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-yellow-default)',
    icon: 'regular-discount',
    favicon: 'icon-module-marketing.svg',
    entity: 'promotion',

    routes: {
        index: {
            components: {
                default: 'sw-promotion-v2-list',
            },
            path: 'index',
            meta: {
                privilege: 'promotion.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        create: {
            component: 'sw-promotion-v2-detail',
            path: 'create',
            redirect: {
                name: 'sw.promotion.v2.create.base',
            },
            meta: {
                privilege: 'promotion.creator',
            },
            children: {
                base: {
                    component: 'sw-promotion-v2-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.promotion.v2.index',
                        privilege: 'promotion.creator',
                    },
                },
            },
        },

        detail: {
            component: 'sw-promotion-v2-detail',
            path: 'detail/:id?',
            redirect: {
                name: 'sw.promotion.v2.detail.base',
            },
            meta: {
                privilege: 'promotion.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            children: {
                base: {
                    component: 'sw-promotion-v2-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.promotion.v2.index',
                        privilege: 'promotion.viewer',
                    },
                },
                conditions: {
                    component: 'sw-promotion-v2-conditions',
                    path: 'conditions',
                    meta: {
                        parentPath: 'sw.promotion.v2.index',
                        privilege: 'promotion.viewer',
                    },
                },
                discounts: {
                    component: 'sw-promotion-detail-discounts',
                    path: 'discounts',
                    meta: {
                        parentPath: 'sw.promotion.v2.index',
                        privilege: 'promotion.viewer',
                    },
                },
            },
            props: {
                default: (route) => {
                    return {
                        promotionId: route.params.id?.toLowerCase(),
                    };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-marketing',
            label: 'global.sw-admin-menu.navigation.mainMenuItemMarketing',
            color: 'var(--sw-color-module-yellow-default)',
            icon: 'regular-megaphone',
            position: 60,
            privilege: 'promotion.viewer',
        },
        {
            id: 'sw-promotion-v2',
            path: 'sw.promotion.v2.index',
            label: 'sw-promotion-v2.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-yellow-default)',
            icon: 'regular-discount',
            position: 10,
            parent: 'sw-marketing',
            privilege: 'promotion.viewer',
        },
    ],

    defaultSearchConfiguration,
});
