import './component/structure/sw-admin-menu-extension';
import './component/structure/sw-sales-channel-menu';
import './component/sw-sales-channel-defaults-select';
import './component/sw-sales-channel-modal';
import './component/sw-sales-channel-modal-grid';
import './component/sw-sales-channel-modal-detail';

import './component/sw-sales-channel-detail-domains';
import './component/sw-sales-channel-detail-hreflang';

import './page/sw-sales-channel-detail';
import './page/sw-sales-channel-create';
import './view/sw-sales-channel-detail-base';
import './view/sw-sales-channel-detail-products';
import './view/sw-sales-channel-detail-analytics';
import './view/sw-sales-channel-create-base';
import './view/sw-sales-channel-detail-product-comparison';
import './view/sw-sales-channel-detail-product-comparison-preview';
import './service/export-template.service';
import './product-export-templates';
import './component/sw-sales-channel-products-assignment-modal';
import './component/sw-sales-channel-products-assignment-single-products';
import './component/sw-sales-channel-product-assignment-categories';
import './component/sw-sales-channel-products-assignment-dynamic-product-groups';

import './acl';

const { Module } = Shopware;

Module.register('sw-sales-channel', {
    type: 'core',
    name: 'sales-channel',
    title: 'sw-sales-channel.general.titleMenuItems',
    description: 'The module for managing Sales Channels.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#14D7A5',
    icon: 'default-device-server',
    entity: 'sales_channel',

    routes: {
        detail: {
            component: 'sw-sales-channel-detail',
            path: 'detail/:id',
            meta: {
                privilege: 'sales_channel.viewer',
            },
            redirect: {
                name: 'sw.sales.channel.detail.base',
            },
            children: {
                base: {
                    component: 'sw-sales-channel-detail-base',
                    path: 'base',
                    meta: {
                        privilege: 'sales_channel.viewer',
                    },
                },
                products: {
                    component: 'sw-sales-channel-detail-products',
                    path: 'products',
                    meta: {
                        privilege: 'sales_channel.viewer',
                    },
                },
                productComparison: {
                    component: 'sw-sales-channel-detail-product-comparison',
                    path: 'product-comparison',
                    meta: {
                        privilege: 'sales_channel.viewer',
                    },
                },
                analytics: {
                    component: 'sw-sales-channel-detail-analytics',
                    path: 'analytics',
                    meta: {
                        privilege: 'sales_channel.viewer',
                    },
                },
            },
        },

        create: {
            component: 'sw-sales-channel-create',
            path: 'create/:typeId',
            redirect: {
                name: 'sw.sales.channel.create.base',
            },
            children: {
                base: {
                    component: 'sw-sales-channel-create-base',
                    path: 'base',
                    meta: {
                        privilege: 'sales_channel.creator',
                    },
                },
            },
        },
    },
});
