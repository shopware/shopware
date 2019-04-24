import { NEXT134 } from 'src/flag/feature_next134';
import { Module } from 'src/core/shopware';
import './service/cms.service';
import './state/cms-page.state';
import './mixin/sw-cms-element.mixin';
import './blocks';
import './elements';
import './component';
import './page/sw-cms-list';
import './page/sw-cms-detail';
import './page/sw-cms-create';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-cms', {
    flag: NEXT134,
    type: 'core',
    name: 'sw-cms.general.mainMenuItemGeneral',
    description: 'The module for creating content.',
    color: '#ff68b4',
    icon: 'default-object-marketing',
    favicon: 'icon-module-marketing.png',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-cms-list',
            path: 'index',
            meta: {
                noNav: true
            }
        },
        detail: {
            component: 'sw-cms-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.cms.index',
                noNav: true
            }
        },
        create: {
            component: 'sw-cms-create',
            path: 'create',
            meta: {
                parentPath: 'sw.cms.index',
                noNav: true
            }
        }
    },

    navigation: [{
        id: 'sw-cms',
        label: 'sw-cms.general.mainMenuItemGeneral',
        color: '#ff68b4',
        path: 'sw.cms.index',
        icon: 'default-object-marketing',
        position: 45
    }]
});
