/**
 * @sw-package framework
 */
import './acl';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-webhook-list', () => import('./page/sw-settings-webhook-list'));

Shopware.Module.register('sw-settings-webhook', {
    type: 'core',
    name: 'settings-webhook',
    title: 'sw-settings-webhook.general.mainMenuItemGeneral',
    description: 'sw-settings-webhook.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-bolt',
    favicon: 'icon-module-settings.svg',
    entity: 'webhook',

    routes: {
        index: {
            component: 'sw-settings-webhook-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'webhook.viewer',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.webhook.index',
        icon: 'regular-bolt',
        privilege: 'webhook.viewer',
    },
});

export {};
