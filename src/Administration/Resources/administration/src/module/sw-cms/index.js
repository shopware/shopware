import { NEXT134 } from 'src/flag/feature_next134';
import { Module } from 'src/core/shopware';
import './blocks';
import './elements';
import './component';
import './page/sw-cms-list';
import './page/sw-cms-detail';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-cms', {
    flag: NEXT134,
    type: 'core',
    name: 'Content Management',
    description: 'The module for creating content.',
    color: '#ff68b4',
    icon: 'default-object-marketing',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-cms-list',
            path: 'index',
            meta: {
                noNav: true,
                newTab: true
            }
        },
        detail: {
            component: 'sw-cms-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.cms.index',
                noNav: true
            }
        }
    },

    navigation: [{
        id: 'sw-cms',
        label: 'Content Management',
        color: '#ff68b4',
        path: 'sw.cms.index',
        icon: 'default-object-marketing',
        position: 45
    }]
});
