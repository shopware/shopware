/**
 * @sw-package checkout
 */
import './acl';
import './page/sw-settings-salutation-detail/register';
import './page/sw-settings-salutation-list/register';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-salutation', {
    type: 'core',
    name: 'settings-salutation',
    title: 'sw-settings-salutation.general.mainMenuItemGeneral',
    description: 'sw-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-comments',
    favicon: 'icon-module-settings.svg',
    entity: 'salutation',

    routes: {
        index: {
            component: 'sw-settings-salutation-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'salutation.viewer',
            },
        },
        detail: {
            component: 'sw-settings-salutation-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.salutation.index',
                privilege: 'salutation.viewer',
            },
            props: {
                default(route) {
                    return {
                        salutationId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
        create: {
            component: 'sw-settings-salutation-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.salutation.index',
                privilege: 'salutation.creator',
            },
        },
    },

    settingsItem: {
        group: 'customer',
        to: 'sw.settings.salutation.index',
        icon: 'regular-comments',
        privilege: 'salutation.viewer',
    },
});
