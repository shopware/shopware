import './extension/sw-settings-index';
import './page/sw-settings-custom-field-set-create';
import './page/sw-settings-custom-field-set-list';
import './page/sw-settings-custom-field-set-detail';
import './component/sw-custom-field-translated-labels';
import './component/sw-custom-field-set-detail-base';
import './component/sw-custom-field-list';
import './component/sw-custom-field-detail';
import './component/sw-custom-field-type-base';
import './component/sw-custom-field-type-select';
import './component/sw-custom-field-type-text';
import './component/sw-custom-field-type-number';
import './component/sw-custom-field-type-date';
import './component/sw-custom-field-type-checkbox';
import './component/sw-custom-field-type-text-editor';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-custom-field', {
    type: 'core',
    name: 'settings-custom-field',
    title: 'sw-settings-custom-field.general.mainMenuItemGeneral',
    description: 'sw-settings-custom-field.general.description',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'custom-field-set',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-custom-field-set-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-custom-field-set-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.custom.field.index'
            }
        },
        create: {
            component: 'sw-settings-custom-field-set-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.custom.field.index'
            }
        }
    }
});
