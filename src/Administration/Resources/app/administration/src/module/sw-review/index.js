/**
 * @sw-package after-sales
 */
import './acl';
import './page/sw-review-detail/register';
import './page/sw-review-list/register';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-review', {
    type: 'core',
    name: 'Reviews',
    title: 'sw-review.general.mainMenuItemGeneral',
    description: 'sw-review.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-green-default)',
    icon: 'regular-star',
    favicon: 'icon-module-products.svg',
    entity: 'product_review',

    routes: {
        index: {
            components: {
                default: 'sw-review-list',
            },
            path: 'index',
            meta: {
                privilege: 'review.viewer',
            },
        },
        detail: {
            component: 'sw-review-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.review.index',
                privilege: 'review.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'sw-review',
            label: 'sw-review.general.mainMenuItemList',
            color: 'var(--sw-color-module-green-default)',
            path: 'sw.review.index',
            icon: 'regular-star',
            parent: 'sw-catalogue',
            position: 20,
            privilege: 'review.viewer',
        },
    ],
});
