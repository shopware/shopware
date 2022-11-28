import './init/services.init';
import './acl';

import swPromotionState from './page/sw-promotion-v2-detail/state';
import defaultSearchConfiguration from './default-search-configuration';

const { Module, State } = Shopware;
State.registerModule('swPromotionDetail', swPromotionState);

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-promotion-v2-settings-trigger', () => import('./component/discount/sw-promotion-v2-settings-trigger'));
Shopware.Component.register('sw-promotion-v2-settings-discount-type', () => import('./component/discount/sw-promotion-v2-settings-discount-type'));
Shopware.Component.register('sw-promotion-v2-settings-rule-selection', () => import('./component/discount/sw-promotion-v2-settings-rule-selection'));
Shopware.Component.extend('sw-promotion-v2-wizard-discount-selection', 'sw-wizard-page', () => import('./component/discount/sw-promotion-v2-wizard-discount-selection'));
Shopware.Component.register('sw-promotion-v2-wizard-description', () => import('./component/discount/sw-promotion-v2-wizard-description'));
Shopware.Component.register('sw-promotion-v2-generate-codes-modal', () => import('./component/promotion-codes/sw-promotion-v2-generate-codes-modal'));
Shopware.Component.register('sw-promotion-v2-individual-codes-behavior', () => import('./component/promotion-codes/sw-promotion-v2-individual-codes-behavior'));
Shopware.Component.register('sw-promotion-v2-cart-condition-form', () => import('./component/sw-promotion-v2-cart-condition-form'));
Shopware.Component.register('sw-promotion-v2-empty-state-hero', () => import('./component/sw-promotion-v2-empty-state-hero'));
Shopware.Component.register('sw-promotion-v2-rule-select', () => import('./component/sw-promotion-v2-rule-select'));
Shopware.Component.register('sw-promotion-v2-sales-channel-select', () => import('./component/sw-promotion-v2-sales-channel-select'));
Shopware.Component.register('sw-promotion-discount-component', () => import('./component/sw-promotion-discount-component'));
Shopware.Component.register('sw-promotion-v2-detail', () => import('./page/sw-promotion-v2-detail'));
Shopware.Component.register('sw-promotion-v2-list', () => import('./page/sw-promotion-v2-list'));
Shopware.Component.register('sw-promotion-v2-detail-base', () => import('./view/sw-promotion-v2-detail-base'));
Shopware.Component.register('sw-promotion-v2-conditions', () => import('./view/sw-promotion-v2-conditions'));
Shopware.Component.register('sw-promotion-detail-discounts', () => import('./view/sw-promotion-detail-discounts'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-promotion-v2', {
    type: 'core',
    name: 'promotion-v2',
    title: 'sw-promotion-v2.general.mainMenuItemGeneral',
    description: 'sw-promotion-v2.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FFD700',
    icon: 'regular-megaphone',
    favicon: 'regular-megaphone',
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
                        promotionId: route.params.id,
                    };
                },
            },
        },
    },

    navigation: [{
        id: 'sw-marketing',
        label: 'global.sw-admin-menu.navigation.mainMenuItemMarketing',
        color: '#FFD700',
        icon: 'regular-megaphone',
        position: 70,
        privilege: 'promotion.viewer',
    }, {
        id: 'sw-promotion-v2',
        path: 'sw.promotion.v2.index',
        label: 'sw-promotion-v2.general.mainMenuItemGeneral',
        color: '#FFD700',
        icon: 'regular-megaphone',
        position: 100,
        parent: 'sw-marketing',
        privilege: 'promotion.viewer',
    }],

    defaultSearchConfiguration,
});
