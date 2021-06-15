import './component/discount/sw-promotion-v2-settings-trigger';
import './component/discount/sw-promotion-v2-settings-discount-type';
import './component/discount/sw-promotion-v2-settings-rule-selection';
import './component/discount/sw-promotion-v2-wizard-discount-selection';
import './component/discount/sw-promotion-v2-wizard-description';

import './component/promotion-codes/sw-promotion-v2-generate-codes-modal';
import './component/promotion-codes/sw-promotion-v2-individual-codes-behavior';

import './component/sw-promotion-v2-cart-condition-form';
import './component/sw-promotion-v2-empty-state-hero';
import './component/sw-promotion-v2-rule-select';
import './component/sw-promotion-v2-sales-channel-select';

import './init/services.init';

import './page/sw-promotion-v2-detail';
import './page/sw-promotion-v2-list';

import './view/sw-promotion-v2-detail-base';
import './view/sw-promotion-v2-conditions';

import './acl';

import swPromotionState from 'src/module/sw-promotion/page/sw-promotion-detail/state';

const { Module, State } = Shopware;
State.registerModule('swPromotionDetail', swPromotionState);

Module.register('sw-promotion-v2', {
    type: 'core',
    name: 'promotion-v2',
    title: 'sw-promotion-v2.general.mainMenuItemGeneral',
    description: 'sw-promotion-v2.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FFD700',
    icon: 'default-object-marketing',
    favicon: 'default-object-marketing',
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
        icon: 'default-object-marketing',
        position: 70,
        privilege: 'promotion.viewer',
    }, {
        id: 'sw-promotion-v2',
        path: 'sw.promotion.v2.index',
        label: 'sw-promotion-v2.general.mainMenuItemGeneral',
        color: '#FFD700',
        icon: 'default-object-marketing',
        position: 100,
        parent: 'sw-marketing',
        privilege: 'promotion.viewer',
    }],
});
