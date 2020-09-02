import './component/sw-category-tree';
import './component/sw-category-view';
import './component/sw-category-sales-channel-card';
import './component/sw-category-link-settings';
import './component/sw-category-layout-card';
import './component/sw-category-detail-menu';
import './component/sw-category-seo-form';
import './page/sw-category-detail';
import './view/sw-category-detail-base';
import './view/sw-category-detail-cms';
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
                privilege: 'category.viewer'
            }
        },

        detail: {
            component: 'sw-category-detail',
            path: 'index/:id',
            meta: {
                privilege: 'category.viewer'
            },
            redirect: {
                name: 'sw.category.detail.base'
            },

            children: {
                base: {
                    component: 'sw-category-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.category.index',
                        privilege: 'category.viewer'
                    }
                },
                cms: {
                    component: 'sw-category-detail-cms',
                    path: 'cms',
                    meta: {
                        parentPath: 'sw.category.index',
                        privilege: 'category.viewer' // change in NEXT-8921 to CMS rights
                    }
                }
            },

            props: {
                default(route) {
                    return {
                        categoryId: route.params.id
                    };
                }
            }
        }
    },

    navigation: [{
        id: 'sw-category',
        path: 'sw.category.index',
        label: 'sw-category.general.mainMenuItemIndex',
        parent: 'sw-catalogue',
        privilege: 'category.viewer',
        position: 20
    }]
});
