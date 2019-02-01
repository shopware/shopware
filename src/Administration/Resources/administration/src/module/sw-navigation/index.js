import { Module } from 'src/core/shopware';
import './component/sw-navigation-tree';
import './component/sw-navigation-view';
import './page/sw-navigation-detail';

Module.register('sw-navigation', {
    type: 'core',
    name: 'Navigations',
    description: 'The module for managing navigations.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-package-closed',

    routes: {
        index: {
            component: 'sw-navigation-detail',
            path: 'index',
            meta: {
                parentPath: 'sw.navigation.index'
            }
        },
        detail: {
            component: 'sw-navigation-detail',
            path: 'index/:id',
            meta: {
                parentPath: 'sw.navigation.index'
            }
        }
    },

    navigation: [{
        id: 'sw-navigation',
        path: 'sw.navigation.index',
        label: 'sw-navigation.general.mainMenuItemIndex',
        parent: 'sw-product'
    }]
});
