/**
 * @package content
 */
import './acl';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-category-tree', () => import('./component/sw-category-tree'));
Shopware.Component.register('sw-landing-page-tree', () => import('./component/sw-landing-page-tree'));
Shopware.Component.register('sw-landing-page-view', () => import('./component/sw-landing-page-view'));
Shopware.Component.register('sw-category-view', () => import('./component/sw-category-view'));
Shopware.Component.register('sw-category-link-settings', () => import('./component/sw-category-link-settings'));
Shopware.Component.register('sw-category-layout-card', () => import('./component/sw-category-layout-card'));
Shopware.Component.register('sw-category-detail-menu', () => import('./component/sw-category-detail-menu'));
Shopware.Component.register('sw-category-seo-form', () => import('./component/sw-category-seo-form'));
Shopware.Component.register('sw-category-entry-point-card', () => import('./component/sw-category-entry-point-card'));
Shopware.Component.register('sw-category-entry-point-modal', () => import('./component/sw-category-entry-point-modal'));
Shopware.Component.register('sw-category-entry-point-overwrite-modal', () => import('./component/sw-category-entry-point-overwrite-modal'));
Shopware.Component.extend('sw-category-sales-channel-multi-select', 'sw-entity-multi-select', () => import('./component/sw-category-sales-channel-multi-select'));
Shopware.Component.register('sw-category-detail', () => import('./page/sw-category-detail'));
Shopware.Component.register('sw-category-detail-base', () => import('./view/sw-category-detail-base'));
Shopware.Component.register('sw-category-detail-cms', () => import('./view/sw-category-detail-cms'));
Shopware.Component.register('sw-category-detail-custom-entity', () => import('./view/sw-category-detail-custom-entity'));
Shopware.Component.register('sw-landing-page-detail-base', () => import('./view/sw-landing-page-detail-base'));
Shopware.Component.register('sw-landing-page-detail-cms', () => import('./view/sw-landing-page-detail-cms'));
Shopware.Component.register('sw-category-detail-products', () => import('./view/sw-category-detail-products'));
Shopware.Component.register('sw-category-detail-seo', () => import('./view/sw-category-detail-seo'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-category', {
    type: 'core',
    name: 'category',
    title: 'sw-category.general.mainMenuItemIndex',
    description: 'sw-category.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'regular-products',
    favicon: 'icon-module-products.png',
    entity: 'category',

    searchMatcher: (regex, labelType, manifest) => {
        const match = labelType.toLowerCase().match(regex);

        if (!match) {
            return false;
        }

        return [
            {
                name: manifest.name,
                icon: manifest.icon,
                color: manifest.color,
                label: labelType,
                entity: manifest.entity,
                route: manifest.routes.index,
                privilege: manifest.routes.index?.meta.privilege,
            },
            {
                name: manifest.name,
                icon: manifest.icon,
                color: manifest.color,
                route: { ...manifest.routes.landingPageDetail, params: { id: 'create' } },
                entity: 'landing_page',
                privilege: manifest.routes.landingPageDetail?.meta.privilege,
                action: true,
            },
        ];
    },

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
                appSystem: {
                    view: 'detail',
                },
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
                customEntity: {
                    component: 'sw-category-detail-custom-entity',
                    path: 'customEntity',
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

    defaultSearchConfiguration,
});
