import './component/structure/sw-admin-menu-extension';
import './component/structure/sw-sales-channel-menu';
import './component/sw-sales-channel-defaults-select';
import './component/sw-sales-channel-modal';
import './component/sw-sales-channel-modal-grid';
import './component/sw-sales-channel-modal-detail';

import './component/sw-sales-channel-detail-domains';
import './component/sw-sales-channel-detail-hreflang';

import './component/sw-sales-channel-detail-protect-link';
import './component/sw-sales-channel-detail-account-connect';
import './component/sw-sales-channel-detail-account-disconnect';

import './page/sw-sales-channel-detail';
import './page/sw-sales-channel-create';
import './view/sw-sales-channel-detail-base';
import './view/sw-sales-channel-detail-analytics';
import './view/sw-sales-channel-detail-products';
import './view/sw-sales-channel-create-base';
import './view/sw-sales-channel-detail-product-comparison';
import './view/sw-sales-channel-detail-product-comparison-preview';
import './service/export-template.service';
import './product-export-templates';

import './component/sw-sales-channel-google-programs-modal';
import './component/sw-sales-channel-google-introduction';
import './component/sw-sales-channel-google-authentication';
import './component/sw-sales-channel-google-merchant';
import './component/sw-sales-channel-google-shipping-setting';

const { Module } = Shopware;

Module.register('sw-sales-channel', {
    type: 'core',
    name: 'sales-channel',
    title: 'sw-sales-channel.general.titleMenuItems',
    description: 'The module for managing sales channels.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#14D7A5',
    icon: 'default-device-server',
    entity: 'sales_channel',

    routes: {
        detail: {
            component: 'sw-sales-channel-detail',
            path: 'detail/:id',
            redirect: {
                name: 'sw.sales.channel.detail.base'
            },
            children: {
                base: {
                    component: 'sw-sales-channel-detail-base',
                    path: 'base',
                    children: {
                        'step-1': {
                            component: 'sw-sales-channel-google-introduction',
                            path: 'step-1'
                        },
                        'step-2': {
                            component: 'sw-sales-channel-google-authentication',
                            path: 'step-2'
                        },
                        'step-3': {
                            component: 'sw-sales-channel-google-merchant',
                            path: 'step-3'
                        },
                        'step-7': {
                            component: 'sw-sales-channel-google-shipping-setting',
                            path: 'step-7'
                        }
                    }
                },
                productComparison: {
                    component: 'sw-sales-channel-detail-product-comparison',
                    path: 'product-comparison'
                },
                analytics: {
                    component: 'sw-sales-channel-detail-analytics',
                    path: 'analytics'
                },
                products: {
                    component: 'sw-sales-channel-detail-products',
                    path: 'products'
                }
            }
        },

        create: {
            component: 'sw-sales-channel-create',
            path: 'create/:typeId',
            redirect: {
                name: 'sw.sales.channel.create.base'
            },
            children: {
                base: {
                    component: 'sw-sales-channel-create-base',
                    path: 'base'
                }
            }
        }
    }
});
