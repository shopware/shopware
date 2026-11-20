/**
 * @sw-package framework
 */
import './acl';

/**
 * @private
 */
Shopware.Component.register('sw-settings-webhook-list', () => import('./page/sw-settings-webhook-list'));

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-webhook-deliveries-modal',
    () => import('./component/sw-settings-webhook-deliveries-modal'),
);

/**
 * @private
 */
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

/**
 * @private
 */
export {};
