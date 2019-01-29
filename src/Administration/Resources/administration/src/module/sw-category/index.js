import { Module } from 'src/core/shopware';
import { NEXT716 } from 'src/flag/feature_next716';
import './component/sw-category-tree';
import './component/sw-category-view';
import './page/sw-category-detail';

Module.register('sw-category', {
    flag: NEXT716,
    type: 'core',
    name: 'Categories',
    description: 'The module for managing categories.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-package-closed',

    routes: {
        index: {
            component: 'sw-category-detail',
            path: 'index',
            meta: {
                parentPath: 'sw.category.index'
            }
        },
        detail: {
            component: 'sw-category-detail',
            path: 'index/:id',
            meta: {
                parentPath: 'sw.category.index'
            }
        }
    },

    navigation: [{
        id: 'sw-category',
        path: 'sw.category.index',
        label: 'sw-category.general.mainMenuItemIndex',
        parent: 'sw-product'
    }]
});
