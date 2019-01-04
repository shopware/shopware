import { Module } from 'src/core/shopware';
import './page/sw-configuration-list';
import './page/sw-configuration-detail';
import './page/sw-configuration-create';
import './component/sw-configuration-option-detail';
import './component/sw-configuration-detail-base';
import './component/sw-configuration-option-list';
import { NEXT719 } from '../../flag/feature_next719';

Module.register('sw-configuration', {
    type: 'core',
    name: 'Configuration',
    description: 'sw-configuration.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    flag: NEXT719,
    color: '#57D9A3',
    icon: 'default-symbol-products',

    routes: {
        index: {
            components: {
                default: 'sw-configuration-list'
            },
            path: 'index'
        },
        detail: {
            component: 'sw-configuration-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.configuration.index'
            }
        },
        create: {
            component: 'sw-configuration-create',
            path: 'create',
            meta: {
                parentPath: 'sw.configuration.index'
            }
        },
        option: {
            component: 'sw-configuration-option-detail',
            path: 'detail/:groupId/option/:optionId',
            meta: {
                parentPath: 'sw.configuration.detail'
            }
        }
    },

    navigation: [{
        id: 'sw-configuration',
        label: 'sw-configuration.general.mainMenuItemGeneral',
        parent: 'sw-product',
        path: 'sw.configuration.index'
    }]
});
