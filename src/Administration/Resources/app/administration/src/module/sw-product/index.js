/**
 * @sw-package inventory
 */

import './acl';
import defaultSearchConfiguration from './default-search-configuration';
import './component/sw-product-media-form/register';
import './page/sw-product-list/register';
import './component/sw-product-add-properties-modal/register';
import './component/sw-product-basic-form/register';
import './component/sw-product-category-form/register';
import './component/sw-product-clone-modal/register';
import './component/sw-product-cross-selling-assignment/register';
import './component/sw-product-cross-selling-form/register';
import './component/sw-product-deliverability-downloadable-form/register';
import './component/sw-product-deliverability-form/register';
import './component/sw-product-download-form/register';
import './component/sw-product-feature-set-form/register';
import './component/sw-product-guarantee-form/register';
import './component/sw-product-layout-assignment/register';
import './component/sw-product-measurement-form/register';
import './component/sw-product-packaging-form/register';
import './component/sw-product-price-form/register';
import './component/sw-product-properties/register';
import './component/sw-product-seo-form/register';
import './component/sw-product-settings-form/register';
import './component/sw-product-settings-mode/register';
import './component/sw-product-variant-modal/register';
import './component/sw-product-variants/sw-product-modal-delivery/register';
import './component/sw-product-variants/sw-product-modal-variant-generation/register';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-restriction-selection/register';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-prices/register';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-restrictions/register';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-selection/register';
import './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-price-field/register';
import './component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-listing/register';
import './component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-media/register';
import './component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-order/register';
import './component/sw-product-variants/sw-product-variants-media-upload/register';
import './component/sw-product-variants/sw-product-variants-overview/register';
import './component/sw-product-visibility-detail/register';
import './component/sw-product-visibility-select/register';
import './view/sw-product-detail-base/register';
import './view/sw-product-detail-context-prices/register';
import './view/sw-product-detail-cross-selling/register';
import './view/sw-product-detail-layout/register';
import './view/sw-product-detail-reviews/register';
import './view/sw-product-detail-seo/register';
import './view/sw-product-detail-specifications/register';
import './view/sw-product-detail-variants/register';

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
/**
 * @deprecated tag:v6.8.0 - File will be removed. No longer used.
 */
if (!Shopware.Feature.isActive('v6.8.0.0')) {
}
Shopware.Component.register('sw-product-detail', () => import('./page/sw-product-detail'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-product', {
    type: 'core',
    name: 'product',
    title: 'sw-product.general.mainMenuItemGeneral',
    description: 'sw-product.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-green-default)',
    icon: 'regular-products',
    favicon: 'icon-module-products.svg',
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
            props: {
                default: (route) => {
                    const props = {
                        creationType: route.query.creationType ?? 'physical',
                    };

                    if (!Shopware.Feature.isActive('v6.8.0.0')) {
                        props.creationStates = route.query.creationStates ?? ['is-physical'];
                    }

                    return props;
                },
            },
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
                default: (route) => ({ productId: route.params.id?.toLowerCase() }),
            },
            redirect: {
                name: 'sw.product.detail.base',
            },
            meta: {
                privilege: 'product.viewer',
                appSystem: {
                    view: 'detail',
                },
                parentPath: 'sw.product.index',
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

    navigation: [
        {
            id: 'sw-catalogue',
            label: 'global.sw-admin-menu.navigation.mainMenuItemProducts',
            color: 'var(--sw-color-module-green-default)',
            icon: 'regular-products',
            position: 20,
        },
        {
            id: 'sw-product',
            label: 'sw-product.general.mainMenuItemOverview',
            color: 'var(--sw-color-module-green-default)',
            path: 'sw.product.index',
            icon: 'regular-products',
            parent: 'sw-catalogue',
            privilege: 'product.viewer',
            position: 10,
        },
    ],

    defaultSearchConfiguration,
});
