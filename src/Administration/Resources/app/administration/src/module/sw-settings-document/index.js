import './page/sw-settings-document-list';
import './page/sw-settings-document-detail';

import './acl';

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

    routes: {
        index: {
            component: 'sw-settings-document-list',
            path: 'index',
            meta: {
                privilege: 'document.viewer',
                parentPath: 'sw.settings.index',
            },
        },
        detail: {
            component: 'sw-settings-document-detail',
            path: 'detail/:id',
            meta: {
                privilege: 'document.viewer',
                parentPath: 'sw.settings.document.index',
            },
            props: {
                default: (route) => ({ documentConfigId: route.params.id }),
            },
        },
        create: {
            component: 'sw-settings-document-detail',
            path: 'create',
            meta: {
                privilege: 'document.creator',
                parentPath: 'sw.settings.document.index',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.document.index',
        privilege: 'document.viewer',
        icon: 'default-documentation-file',
    },
});
