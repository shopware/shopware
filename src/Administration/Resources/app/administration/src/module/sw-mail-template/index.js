import './acl';
import './component/sw-mail-header-footer-list/register';
import './component/sw-mail-template-list/register';
import './component/sw-mail-template-preview-modal/register';
import './page/sw-mail-header-footer-detail/register';
import './page/sw-mail-header-footer-create/register';
import './page/sw-mail-template-detail/register';
import './page/sw-mail-template-create/register';
import './page/sw-mail-template-index/register';
import './view/sw-mail-template-view-header-footer/register';
import './view/sw-mail-template-view-templates/register';

const { Module, Feature } = Shopware;

/**
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-mail-template', {
    type: 'core',
    name: 'mail-template',
    title: 'sw-mail-template.general.mainMenuItemGeneral',
    description: 'Manages the mail templates of the application',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-envelope',
    favicon: 'icon-module-settings.svg',
    entity: 'mail_template',

    routes: {
        index: (() => {
            const route = {
                component: 'sw-mail-template-index',
                path: 'index',
                meta: {
                    parentPath: 'sw.settings.index',
                    privilege: 'mail_templates.viewer',
                },
            };

            if (Feature.isActive('V6_8_0_0')) {
                route.redirect = { name: 'sw.mail.template.index.templates' };
                route.children = {
                    templates: {
                        component: 'sw-mail-template-view-templates',
                        path: 'templates',
                        meta: {
                            parentPath: 'sw.settings.index',
                            privilege: 'mail_templates.viewer',
                        },
                    },
                    header_footer: {
                        component: 'sw-mail-template-view-header-footer',
                        path: 'header-footer',
                        meta: {
                            parentPath: 'sw.settings.index',
                            privilege: 'mail_templates.viewer',
                        },
                    },
                };
            }

            return route;
        })(),
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
                parentPath: Feature.isActive('V6_8_0_0') ? 'sw.mail.template.index.header_footer' : 'sw.mail.template.index',
                privilege: 'mail_templates.creator',
            },
        },
        detail_head_foot: {
            component: 'sw-mail-header-footer-detail',
            path: 'detail-head-foot/:id',
            meta: {
                parentPath: Feature.isActive('V6_8_0_0') ? 'sw.mail.template.index.header_footer' : 'sw.mail.template.index',
                privilege: 'mail_templates.viewer',
            },
        },
    },

    settingsItem: {
        group: 'content',
        to: 'sw.mail.template.index',
        icon: 'regular-envelope',
        privilege: 'mail_templates.viewer',
    },
});
