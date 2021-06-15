import './page/sw-integration-list';
import './acl';

const { Module } = Shopware;

Module.register('sw-integration', {
    type: 'core',
    name: 'integration',
    title: 'sw-integration.general.mainMenuItemIndex',
    description: 'The module for managing integrations.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'integration',

    routes: {
        index: {
            component: 'sw-integration-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'integration.viewer',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.integration.index',
        icon: 'default-action-settings',
        privilege: 'integration.viewer',
    },
});
