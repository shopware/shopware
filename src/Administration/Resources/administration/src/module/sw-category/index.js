import { Module } from 'src/core/shopware';
import './page/sw-category-detail';

Module.register('sw-category', {
    type: 'core',
    name: 'Categories',
    description: 'The module for managing categories.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FF85C2',
    icon: 'default-package-closed',

    routes: {
        index: {
            component: 'sw-category-detail',
            path: 'index'
        },
        detail: {
            component: 'sw-category-detail',
            path: 'index/:id'
        }
    },

    navigation: [
        {
            id: 'sw-category',
            path: 'sw.category.index',
            label: 'sw-category.general.mainMenuItemIndex',
            parent: 'sw-product'
        }
    ]
});
