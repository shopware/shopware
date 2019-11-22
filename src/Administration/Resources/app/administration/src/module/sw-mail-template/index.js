import './extension/sw-settings-index';
import './component/sw-mail-template-list';
import './component/sw-mail-header-footer-list';
import './page/sw-mail-template-detail';
import './page/sw-mail-template-create';
import './page/sw-mail-template-index';
import './page/sw-mail-header-footer-detail';
import './page/sw-mail-header-footer-create';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-mail-template', {
    type: 'core',
    name: 'mail-template',
    title: 'sw-mail-template.general.mainMenuItemGeneral',
    description: 'Manages the mail templates of the application',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
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
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
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
    }
});
