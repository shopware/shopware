import './component/sw-promotion-rule-select';
import './component/sw-promotion-sales-channel-select';

import './component/sw-promotion-basic-form';
import './component/sw-promotion-code-form';
import './component/sw-promotion-order-condition-form';
import './component/sw-promotion-persona-form';
import './component/sw-promotion-discount-component';
import './component/sw-promotion-individualcodes';
import './component/sw-promotion-cart-condition-form';
import './view/sw-promotion-detail-base';
import './view/sw-promotion-detail-discounts';
import './view/sw-promotion-detail-restrictions';

import './page/sw-promotion-detail';
import './page/sw-promotion-list';

import './acl/index';

const { Module } = Shopware;

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
Module.register('sw-promotion', {
    flag: 'FEATURE_NEXT_13810',
    type: 'core',
    name: 'promotion',
    title: 'sw-promotion.general.mainMenuItemGeneral',
    description: 'sw-promotion.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FFD700',
    icon: 'default-package-gift',
    favicon: 'default-object-marketing',
    entity: 'promotion',

    routes: {
        index: {
            components: {
                default: 'sw-promotion-list',
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
            component: 'sw-promotion-detail',
            path: 'create',
            redirect: {
                name: 'sw.promotion.create.base',
            },
            meta: {
                privilege: 'promotion.creator',
            },
            children: {
                base: {
                    component: 'sw-promotion-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.promotion.index',
                        privilege: 'promotion.creator',
                    },
                },
            },
        },

        detail: {
            component: 'sw-promotion-detail',
            path: 'detail/:id?',
            redirect: {
                name: 'sw.promotion.detail.base',
            },
            meta: {
                privilege: 'promotion.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            children: {
                base: {
                    component: 'sw-promotion-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.promotion.index',
                        privilege: 'promotion.viewer',
                    },
                },
                restrictions: {
                    component: 'sw-promotion-detail-restrictions',
                    path: 'restrictions',
                    meta: {
                        parentPath: 'sw.promotion.index',
                        privilege: 'promotion.viewer',
                    },
                },
                discounts: {
                    component: 'sw-promotion-detail-discounts',
                    path: 'discounts',
                    meta: {
                        parentPath: 'sw.promotion.index',
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
        id: 'sw-promotion',
        path: 'sw.promotion.index',
        label: 'sw-promotion.general.mainMenuItemGeneral',
        color: '#FFD700',
        icon: 'default-package-gift',
        position: 100,
        parent: 'sw-marketing',
        privilege: 'promotion.viewer',
    }],
});
