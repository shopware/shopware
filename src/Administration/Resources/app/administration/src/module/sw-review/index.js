/**
 * @package content
 */
import './acl';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-review-list', () => import('./page/sw-review-list'));
Shopware.Component.register('sw-review-detail', () => import('./page/sw-review-detail'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-review', {
    type: 'core',
    name: 'Reviews',
    title: 'sw-review.general.mainMenuItemGeneral',
    description: 'sw-review.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'regular-products',
    favicon: 'icon-module-products.png',
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

    navigation: [{
        id: 'sw-review',
        label: 'sw-review.general.mainMenuItemList',
        color: '#57D9A3',
        path: 'sw.review.index',
        icon: 'regular-products',
        parent: 'sw-catalogue',
        position: 20,
        privilege: 'review.viewer',
    }],
});
