/**
 * @sw-package after-sales
 */
import './acl';
import './page/sw-settings-document-detail/register';
import './page/sw-settings-document-list/register';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-document', {
    type: 'core',
    name: 'settings-document',
    title: 'sw-settings-document.general.mainMenuItemGeneral',
    description: 'sw-settings-document.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-file-text',
    favicon: 'icon-module-settings.svg',
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
                default: (route) => ({ documentConfigId: route.params.id.toLowerCase() }),
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
        group: 'commerce',
        to: 'sw.settings.document.index',
        privilege: 'document.viewer',
        icon: 'regular-file-text',
    },
});
