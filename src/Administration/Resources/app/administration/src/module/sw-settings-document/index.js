import './extension/sw-settings-index';
import './page/sw-settings-document-list';
import './page/sw-settings-document-detail';
import './page/sw-settings-document-create';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-document', {
    type: 'core',
    name: 'settings-document',
    title: 'sw-settings-document.general.mainMenuItemGeneral',
    description: 'sw-settings-document.general.description',
    color: '#9AA8B5',
    icon: 'default-documentation-file',
    favicon: 'icon-module-settings.png',
    entity: 'document',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-document-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-document-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.document.index'
            }
        },
        create: {
            component: 'sw-settings-document-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.document.index'
            }
        }
    }
});
