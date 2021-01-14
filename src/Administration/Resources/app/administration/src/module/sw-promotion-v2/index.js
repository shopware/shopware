import './component/sw-promotion-v2-generate-codes-modal';
import './component/sw-promotion-v2-individual-codes-behavior';
import './component/sw-promotion-v2-sales-channel-select';

import './init/services.init';

import './page/sw-promotion-v2-detail';
import './page/sw-promotion-v2-list';

import './view/sw-promotion-v2-detail-base';
import './view/sw-promotion-v2-discounts';
import './view/sw-promotion-v2-conditions';

import './component/sw-promotion-v2-wizard-discount-selection';
import './component/sw-promotion-v2-wizard-description';
import './component/sw-promotion-v2-wizard-shipping-discount-trigger';

const { Module } = Shopware;

Module.register('sw-promotion-v2', {
    flag: 'FEATURE_NEXT_12016',
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
                default: 'sw-promotion-v2-list'
            },
            path: 'index',
            meta: {
                privilege: 'promotion.viewer',
                appSystem: {
                    view: 'list'
                }
            }

        },

        create: {
            component: 'sw-promotion-v2-detail',
            path: 'create',
            redirect: {
                name: 'sw.promotion.v2.create.base'
            },
            meta: {
                privilege: 'promotion.creator'
            },
            children: {
                base: {
                    component: 'sw-promotion-v2-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.promotion.v2.index',
                        privilege: 'promotion.creator'
                    }
                }
            }
        },

        detail: {
            component: 'sw-promotion-v2-detail',
            path: 'detail/:id?',
            redirect: {
                name: 'sw.promotion.v2.detail.base'
            },
            meta: {
                privilege: 'promotion.viewer',
                appSystem: {
                    view: 'detail'
                }
            },
            children: {
                base: {
                    component: 'sw-promotion-v2-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.promotion.v2.index',
                        privilege: 'promotion.viewer'
                    }
                },
                conditions: {
                    component: 'sw-promotion-v2-conditions',
                    path: 'conditions',
                    meta: {
                        parentPath: 'sw.promotion.v2.index',
                        privilege: 'promotion.viewer'
                    }
                },
                discounts: {
                    component: 'sw-promotion-v2-discounts',
                    path: 'discounts',
                    meta: {
                        parentPath: 'sw.promotion.v2.index',
                        privilege: 'promotion.viewer'
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
        position: 70,
        privilege: 'promotion.viewer'
    }, {
        id: 'sw-promotion-v2',
        path: 'sw.promotion.v2.index',
        label: 'sw-promotion-v2.general.mainMenuItemGeneral',
        color: '#FFD700',
        icon: 'default-object-marketing',
        position: 100,
        parent: 'sw-marketing',
        privilege: 'promotion.viewer'
    }]
});
