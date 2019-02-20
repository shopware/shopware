import { Module } from 'src/core/shopware';
import { NEXT754 } from 'src/flag/feature_next754';
import './extension/sw-settings-index';
import './page/sw-settings-attribute-set-create';
import './page/sw-settings-attribute-set-list';
import './page/sw-settings-attribute-set-detail';
import './component/sw-attribute-translated-labels';
import './component/sw-attribute-set-detail-base';
import './component/sw-attribute-list';
import './component/sw-attribute-detail';
import './component/sw-attribute-type-select';
import './component/sw-attribute-type-text';
import './component/sw-attribute-type-media';
import './component/sw-attribute-type-number';
import './component/sw-attribute-type-date';
import './component/sw-attribute-type-checkbox';
import './component/sw-attribute-type-colorpicker';
import './component/sw-attribute-type-text-editor';

Module.register('sw-settings-attribute', {
    flag: NEXT754,
    type: 'core',
    name: 'Attribute settings',
    description: 'sw-settings-attribute.general.description',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    entity: 'attribute-set',

    routes: {
        index: {
            component: 'sw-settings-attribute-set-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-attribute-set-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.attribute.index'
            }
        },
        create: {
            component: 'sw-settings-attribute-set-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.attribute.index'
            }
        }
    },

    navigation: [{
        label: 'sw-settings-attribute.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.attribute.index',
        parent: 'sw-settings'
    }]
});
