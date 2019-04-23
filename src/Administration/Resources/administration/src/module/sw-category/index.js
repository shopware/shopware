import { Module } from 'src/core/shopware';
import { NEXT716 } from 'src/flag/feature_next716';
import './component/sw-category-tree';
import './component/sw-category-view';
import './component/sw-category-select';
import './component/sw-category-leave-page-modal';
import './page/sw-category-detail';
import './view/sw-category-detail-base';
import './view/sw-category-detail-cms';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-category', {
    flag: NEXT716,
    type: 'core',
    name: 'sw-category.general.mainMenuItemIndex',
    description: 'The module for managing categories.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-package-closed',
    favicon: 'icon-module-products.png',
    entity: 'category',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

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
            redirect: {
                name: 'sw.category.detail.base'
            },

            children: {
                base: {
                    component: 'sw-category-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.category.index'
                    }
                },
                cms: {
                    component: 'sw-category-detail-cms',
                    path: 'cms',
                    meta: {
                        parentPath: 'sw.category.index'
                    }
                }
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
