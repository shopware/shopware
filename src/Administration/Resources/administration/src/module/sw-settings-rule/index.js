import './extension/sw-settings-index';
import './page/sw-settings-rule-list';
import './page/sw-settings-rule-detail';
import './page/sw-settings-rule-create';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-rule', {
    type: 'core',
    name: 'settings-rule',
    title: 'sw-settings-rule.general.mainMenuItemGeneral',
    description: 'sw-settings-rule.general.descriptionTextModule',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'rule',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-rule-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-rule-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.rule.index'
            }
        },
        create: {
            component: 'sw-settings-rule-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.rule.index'
            }
        }
    }
});
