import { Module } from 'src/core/shopware';
import { NEXT1271 } from 'src/flag/feature_next1271';
import './page/sw-theme-manager-detail';
import './page/sw-theme-manager-create';
import './page/sw-theme-manager-list';
import './view/sw-theme-manager-detail-base';
import './view/sw-theme-manager-create-base';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-theme-manager', {
    flag: NEXT1271,
    type: 'core',
    name: 'theme-manager',
    description: 'The module for managing sales channels themes.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#D8DDE6',
    icon: 'default-device-server',
    entity: 'theme_manager',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-theme-manager-list',
            path: 'index'
        },
        create: {
            component: 'sw-theme-manager-create',
            path: 'create'
        },
        detail: {
            component: 'sw-theme-manager-detail',
            path: 'detail/:id'
        }
    }
});
