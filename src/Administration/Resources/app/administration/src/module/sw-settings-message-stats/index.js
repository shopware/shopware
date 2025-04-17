/**
 * @sw-package framework
 */
import './page/sw-settings-message-stats';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-message-stats', {
    type: 'core',
    name: 'settings-message-stats',
    title: 'sw-settings-message-stats.general.mainMenuItemGeneral',
    description: 'sw-settings-message-stats.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-message-stats',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.message.stats.index',
        icon: 'regular-chart-line',
        privilege: 'system.system_config',
    },
});
