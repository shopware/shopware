import { Module } from 'src/core/shopware';
import { NEXT700 } from 'src/flag/feature_next700';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';


import './component/sw-promotion-sales-channel-select';

import './component/sw-promotion-basic-form';
import './component/sw-promotion-code-form';
import './component/sw-promotion-order-condition-form';
import './component/sw-promotion-persona-form';
import './component/sw-promotion-rebate-form';
import './component/sw-promotion-scope-form';


import './view/sw-promotion-create-base';
import './view/sw-promotion-detail-base';
import './view/sw-promotion-detail-rebate';
import './view/sw-promotion-detail-restrictions';

import './page/sw-promotion-create';
import './page/sw-promotion-detail';
import './page/sw-promotion-list';

Module.register('sw-promotion', {
    flag: NEXT700,
    type: 'core',
    name: 'sw-promotion.general.module',
    description: 'sw-promotion.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#DE94DE',
    icon: 'default-package-gift',
    entity: 'promotion',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            components: {
                default: 'sw-promotion-list'
            },
            path: 'index'
        },

        create: {
            component: 'sw-promotion-create',
            path: 'create',
            redirect: {
                name: 'sw.promotion.create.base'
            },
            children: {
                base: {
                    component: 'sw-promotion-create-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.promotion.index'
                    }
                }
            }
        },

        detail: {
            component: 'sw-promotion-detail',
            path: 'detail/:id',
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
                rebate: {
                    component: 'sw-promotion-detail-rebate',
                    path: 'rebate',
                    meta: {
                        parentPath: 'sw.promotion.index'
                    }
                }
            }
        }
    },

    navigation: [{
        id: 'sw-promotion',
        path: 'sw.promotion.index',
        label: 'sw-promotion.general.mainMenu.root',
        color: '#DE94DE',
        icon: 'default-package-gift',
        position: 20
    }, {
        path: 'sw.promotion.index',
        label: 'sw-promotion.general.mainMenu.list',
        parent: 'sw-promotion'
    }]
});
