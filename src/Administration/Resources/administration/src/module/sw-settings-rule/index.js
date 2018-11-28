import { Module } from 'src/core/shopware';
import { NEXT516 } from 'src/flag/feature_next516';

import './page/sw-settings-rule-list';

Module.register('sw-settings-rule', {
    flag: NEXT516,
    type: 'core',
    name: 'Rules',
    description: 'Rules section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',

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
    },
    navigation: [{
        label: 'sw-settings-rule.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.rule.index',
        parent: 'sw-settings'
    }]
});
