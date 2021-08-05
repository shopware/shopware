import './component/sw-category-tree';
import './component/sw-landing-page-tree';
import './component/sw-landing-page-view';
import './component/sw-category-view';
import './component/sw-category-sales-channel-card';
import './component/sw-category-link-settings';
import './component/sw-category-layout-card';
import './component/sw-category-detail-menu';
import './component/sw-category-seo-form';
import './component/sw-category-entry-point-card';
import './component/sw-category-entry-point-modal';
import './component/sw-category-entry-point-overwrite-modal';
import './component/sw-category-sales-channel-multi-select';

import './page/sw-category-detail';

import './view/sw-category-detail-base';
import './view/sw-category-detail-cms';
import './view/sw-landing-page-detail-base';
import './view/sw-landing-page-detail-cms';
import './view/sw-category-detail-products';
import './view/sw-category-detail-seo';

import './acl';

const { Module } = Shopware;

Module.register('sw-category', {
    type: 'core',
    name: 'category',
    title: 'sw-category.general.mainMenuItemIndex',
    description: 'sw-category.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    favicon: 'icon-module-products.png',
    entity: 'category',

    routes: {
        index: {
            component: 'sw-category-detail',
            path: 'index',
            meta: {
                parentPath: 'sw.category.index',
                privilege: 'category.viewer',
            },
        },

        detail: {
            component: 'sw-category-detail',
            path: 'index/:id',
            meta: {
                privilege: 'category.viewer',
            },
            redirect: {
                name: 'sw.category.detail.base',
            },

            children: {
                base: {
                    component: 'sw-category-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.category.index',
                        privilege: 'category.viewer',
                    },
                },
                cms: {
                    component: 'sw-category-detail-cms',
                    path: 'cms',
                    meta: {
                        parentPath: 'sw.category.index',
                        privilege: 'category.viewer', // change in NEXT-8921 to CMS rights
                    },
                },
                products: {
                    component: 'sw-category-detail-products',
                    path: 'products',
                    meta: {
                        parentPath: 'sw.category.index',
                        privilege: 'category.viewer',
                    },
                },
                seo: {
                    component: 'sw-category-detail-seo',
                    path: 'seo',
                    meta: {
                        parentPath: 'sw.category.index',
                        privilege: 'category.viewer',
                    },
                },
            },

            props: {
                default(route) {
                    return {
                        categoryId: route.params.id,
                    };
                },
            },
        },

        landingPageDetail: {
            component: 'sw-category-detail',
            path: 'landingPage/:id',
            meta: {
                privilege: 'category.viewer',
            },
            redirect: {
                name: 'sw.category.landingPageDetail.base',
            },

            children: {
                base: {
                    component: 'sw-landing-page-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.category.index',
                        privilege: 'category.viewer',
                    },
                },
                cms: {
                    component: 'sw-landing-page-detail-cms',
                    path: 'cms',
                    meta: {
                        parentPath: 'sw.category.index',
                        privilege: 'category.viewer', // change in NEXT-8921 to CMS rights
                    },
                },
            },

            props: {
                default(route) {
                    return {
                        landingPageId: route.params.id,
                    };
                },
            },
        },
    },

    navigation: [{
        id: 'sw-category',
        path: 'sw.category.index',
        label: 'sw-category.general.mainMenuItemIndex',
        parent: 'sw-catalogue',
        privilege: 'category.viewer',
        position: 20,
    }],
});
