import { Module } from 'src/core/shopware';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

import './page/sw-property-list';
import './page/sw-property-detail';
import './page/sw-property-create';
import './component/sw-property-option-detail';
import './component/sw-property-detail-base';
import './component/sw-property-option-list';
import { NEXT719 } from '../../flag/feature_next719';

Module.register('sw-property', {
    type: 'core',
    name: 'sw-property.general.mainMenuItemGeneral',
    description: 'sw-property.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    flag: NEXT719,
    color: '#57D9A3',
    icon: 'default-symbol-products',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            components: {
                default: 'sw-property-list'
            },
            path: 'index',
            alias: '/'
        },
        detail: {
            component: 'sw-property-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.property.index'
            }
        },
        create: {
            component: 'sw-property-create',
            path: 'create',
            meta: {
                parentPath: 'sw.property.index'
            }
        },
        option: {
            component: 'sw-property-option-detail',
            path: 'detail/:groupId/option/:optionId',
            meta: {
                parentPath: 'sw.property.detail'
            }
        }
    },

    navigation: [{
        id: 'sw-property',
        label: 'sw-property.general.mainMenuItemGeneral',
        parent: 'sw-product',
        path: 'sw.property.index'
    }]
});
