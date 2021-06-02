import './component/sw-product-basic-form';
import './component/sw-product-deliverability-form';
import './component/sw-product-feature-set-form';
import './component/sw-product-category-form';
import './component/sw-product-clone-modal';
import './component/sw-product-variants/sw-product-modal-variant-generation';
import './component/sw-product-variants/sw-product-modal-delivery';
import './component/sw-product-price-form';
import './component/sw-product-settings-form';
import './component/sw-product-packaging-form';
import './component/sw-product-seo-form';
import './component/sw-product-media-form';
import './component/sw-product-visibility-select';
import './component/sw-product-visibility-detail';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-restriction-selection';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-selection';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-prices';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-restrictions';
import './component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-order';
import './component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-media';
import './component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-listing';
import './component/sw-product-variants/sw-product-variants-overview';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-price-field';
import './component/sw-product-variants/sw-product-variants-media-upload';
import './component/sw-product-cross-selling-form';
import './component/sw-product-variant-modal';
import './component/sw-product-cross-selling-assignment';
import './component/sw-product-layout-assignment';
import './component/sw-product-settings-mode';
import './component/sw-product-properties';
import './component/sw-product-add-properties-modal';
import './view/sw-product-detail-base';
import './view/sw-product-detail-specifications';
import './view/sw-product-detail-context-prices';
import './view/sw-product-detail-properties';
import './view/sw-product-detail-variants';
import './view/sw-product-detail-layout';
import './view/sw-product-detail-seo';
import './view/sw-product-detail-cross-selling';
import './view/sw-product-detail-reviews';
import './page/sw-product-list';
import './page/sw-product-detail';
import './acl';

const { Module } = Shopware;

Module.register('sw-product', {
    type: 'core',
    name: 'product',
    title: 'sw-product.general.mainMenuItemGeneral',
    description: 'sw-product.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    favicon: 'icon-module-products.png',
    entity: 'product',

    routes: {
        index: {
            components: {
                default: 'sw-product-list',
            },
            path: 'index',
            meta: {
                privilege: 'product.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        create: {
            component: 'sw-product-detail',
            path: 'create',
            redirect: {
                name: 'sw.product.create.base',
            },
            meta: {
                privilege: 'product.creator',
            },
            children: {
                base: {
                    component: 'sw-product-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.creator',
                    },
                },
            },
        },

        detail: {
            component: 'sw-product-detail',
            path: 'detail/:id?',
            props: {
                default: (route) => ({ productId: route.params.id }),
            },
            redirect: {
                name: 'sw.product.detail.base',
            },
            meta: {
                privilege: 'product.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            children: {
                base: {
                    component: 'sw-product-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                specifications: {
                    component: 'sw-product-detail-specifications',
                    path: 'specifications',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                prices: {
                    component: 'sw-product-detail-context-prices',
                    path: 'prices',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                properties: {
                    component: 'sw-product-detail-properties',
                    path: 'properties',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                variants: {
                    component: 'sw-product-detail-variants',
                    path: 'variants',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                layout: {
                    component: 'sw-product-detail-layout',
                    path: 'layout',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                seo: {
                    component: 'sw-product-detail-seo',
                    path: 'seo',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                crossSelling: {
                    component: 'sw-product-detail-cross-selling',
                    path: 'cross-selling',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                reviews: {
                    component: 'sw-product-detail-reviews',
                    path: 'reviews',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
            },
        },
    },

    navigation: [{
        id: 'sw-catalogue',
        label: 'global.sw-admin-menu.navigation.mainMenuItemCatalogue',
        color: '#57D9A3',
        icon: 'default-symbol-products',
        position: 20,
    }, {
        id: 'sw-product',
        label: 'sw-product.general.mainMenuItemGeneral',
        color: '#57D9A3',
        path: 'sw.product.index',
        icon: 'default-symbol-products',
        parent: 'sw-catalogue',
        privilege: 'product.viewer',
        position: 10,
    }],
});
