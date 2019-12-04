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

const { Module } = Shopware;

Module.register('sw-promotion', {
    type: 'core',
    name: 'promotion',
    title: 'sw-promotion.general.mainMenuItemGeneral',
    description: 'sw-promotion.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FFD700',
    icon: 'default-package-gift',
    favicon: 'icon-module-marketing.png',
    entity: 'promotion',

    routes: {
        index: {
            components: {
                default: 'sw-promotion-list'
            },
            path: 'index'
        },

        create: {
            component: 'sw-promotion-detail',
            path: 'create',
            redirect: {
                name: 'sw.promotion.create.base'
            },
            children: {
                base: {
                    component: 'sw-promotion-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.promotion.index'
                    }
                }
            }
        },

        detail: {
            component: 'sw-promotion-detail',
            path: 'detail/:id?',
            redirect: {
                name: 'sw.promotion.detail.base'
            },
            children: {
                base: {
                    component: 'sw-promotion-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.promotion.index'
                    }
                },
                restrictions: {
                    component: 'sw-promotion-detail-restrictions',
                    path: 'restrictions',
                    meta: {
                        parentPath: 'sw.promotion.index'
                    }
                },
                discounts: {
                    component: 'sw-promotion-detail-discounts',
                    path: 'discounts',
                    meta: {
                        parentPath: 'sw.promotion.index'
                    }
                }
            },
            props: {
                default: (route) => {
                    return {
                        promotionId: route.params.id
                    };
                }
            }
        }
    },

    navigation: [{
        id: 'sw-marketing',
        label: 'global.sw-admin-menu.navigation.mainMenuItemMarketing',
        color: '#FFD700',
        icon: 'default-object-marketing',
        position: 70
    }, {
        id: 'sw-promotion',
        path: 'sw.promotion.index',
        label: 'sw-promotion.general.mainMenuItemGeneral',
        color: '#FFD700',
        icon: 'default-package-gift',
        position: 100,
        parent: 'sw-marketing'
    }]
});
