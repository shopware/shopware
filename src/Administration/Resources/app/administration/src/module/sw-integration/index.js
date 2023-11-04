/**
 * @package system-settings
 */
import './acl';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-integration-list', () => import('./page/sw-integration-list'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-integration', {
    type: 'core',
    name: 'integration',
    title: 'sw-integration.general.mainMenuItemIndex',
    description: 'The module for managing integrations.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'integration',

    routes: {
        index: {
            component: 'sw-integration-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'integration.viewer',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.integration.index',
        icon: 'regular-cog',
        privilege: 'integration.viewer',
    },
});
