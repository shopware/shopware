import { Module } from 'src/core/shopware';
import './component/sw-mail-template-list';
import './component/sw-mail-header-footer-list';
import './component/sw-mail-template-media-form';
import './page/sw-mail-template-detail';
import './page/sw-mail-template-create';
import './page/sw-mail-template-index';
import './page/sw-mail-header-footer-detail';
import './page/sw-mail-header-footer-create';
import { NEXT712 } from 'src/flag/feature_next712';
import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-mail-template', {
    type: 'core',
    name: 'sw-mail-template.general.mainMenuItemGeneral',
    flag: NEXT712,
    description: 'Manages the mail templates of the application',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#c64e3f',
    icon: 'default-symbol-products',
    entity: 'mail_template',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            components: {
                default: 'sw-mail-template-index'
            },
            path: 'index'
        },
        create: {
            component: 'sw-mail-template-create',
            path: 'create',
            meta: {
                parentPath: 'sw.mail.template.index'
            }
        },
        detail: {
            component: 'sw-mail-template-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.mail.template.index'
            }
        },
        create_head_foot: {
            component: 'sw-mail-header-footer-create',
            path: 'create-head-foot',
            meta: {
                parentPath: 'sw.mail.template.index'
            }
        },
        detail_head_foot: {
            component: 'sw-mail-header-footer-detail',
            path: 'detail-head-foot/:id',
            meta: {
                parentPath: 'sw.mail.template.index'
            }
        }
    },

    navigation: [{
        id: 'sw-mail-template',
        label: 'sw-mail-template.general.mainMenuItemGeneral',
        color: '#c64e3f',
        path: 'sw.mail.template.index',
        icon: 'default-symbol-products',
        position: 80
    }, {
        path: 'sw.mail.template.index',
        label: 'sw-mail-template.general.mainMenuItemList',
        parent: 'sw-mail-template'
    }]
});
