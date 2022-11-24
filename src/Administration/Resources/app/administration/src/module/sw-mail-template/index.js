import './acl';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-mail-template-list', () => import('./component/sw-mail-template-list'));
Shopware.Component.register('sw-mail-header-footer-list', () => import('./component/sw-mail-header-footer-list'));
Shopware.Component.register('sw-mail-template-detail', () => import('./page/sw-mail-template-detail'));
Shopware.Component.extend('sw-mail-template-create', 'sw-mail-template-detail', () => import('./page/sw-mail-template-create'));
Shopware.Component.register('sw-mail-template-index', () => import('./page/sw-mail-template-index'));
Shopware.Component.register('sw-mail-header-footer-detail', () => import('./page/sw-mail-header-footer-detail'));
Shopware.Component.extend('sw-mail-header-footer-create', 'sw-mail-header-footer-detail', () => import('./page/sw-mail-header-footer-create'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-mail-template', {
    type: 'core',
    name: 'mail-template',
    title: 'sw-mail-template.general.mainMenuItemGeneral',
    description: 'Manages the mail templates of the application',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'mail_template',

    routes: {
        index: {
            components: {
                default: 'sw-mail-template-index',
            },
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'mail_templates.viewer',
            },
        },
        create: {
            component: 'sw-mail-template-create',
            path: 'create',
            meta: {
                parentPath: 'sw.mail.template.index',
                privilege: 'mail_templates.creator',
            },
        },
        detail: {
            component: 'sw-mail-template-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.mail.template.index',
                privilege: 'mail_templates.viewer',
            },
        },
        create_head_foot: {
            component: 'sw-mail-header-footer-create',
            path: 'create-head-foot',
            meta: {
                parentPath: 'sw.mail.template.index',
                privilege: 'mail_templates.creator',
            },
        },
        detail_head_foot: {
            component: 'sw-mail-header-footer-detail',
            path: 'detail-head-foot/:id',
            meta: {
                parentPath: 'sw.mail.template.index',
                privilege: 'mail_templates.viewer',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.mail.template.index',
        icon: 'regular-envelope',
        privilege: 'mail_templates.viewer',
    },
});
