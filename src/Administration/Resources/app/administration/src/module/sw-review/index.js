import './page/sw-review-list';
import './page/sw-review-detail';

const { Module } = Shopware;

Module.register('sw-review', {
    type: 'core',
    name: 'Reviews',
    description: 'sw-review.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    favicon: 'icon-module-products.png',
    entity: 'product_review',

    routes: {
        index: {
            components: {
                default: 'sw-review-list'
            },
            path: 'index'
        },
        detail: {
            component: 'sw-review-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.review.index'
            }
        }
    },

    navigation: [{
        id: 'sw-review',
        label: 'sw-review.general.mainMenuItemList',
        color: '#57D9A3',
        path: 'sw.review.index',
        icon: 'default-symbol-products',
        parent: 'sw-catalogue',
        position: 20
    }]

});
