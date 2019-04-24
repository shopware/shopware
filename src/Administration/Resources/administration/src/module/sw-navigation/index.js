import { Module } from 'src/core/shopware';
import './component/sw-navigation-tree';
import './component/sw-navigation-view';
import './page/sw-navigation-detail';
import { NEXT1594 } from '../../flag/feature_next1594';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-navigation', {
    type: 'core',
    flag: NEXT1594,
    name: 'sw-navigation.general.mainMenuItemIndex',
    description: 'sw-navigation.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-package-closed',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

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
