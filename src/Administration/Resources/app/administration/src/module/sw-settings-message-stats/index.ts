/**
 * @sw-package framework
 */
import './page/sw-settings-message-stats/register';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Module.register('sw-settings-message-stats', {
    type: 'core',
    name: 'settings-message-stats',
    title: 'sw-settings-message-stats.general.mainMenuItemGeneral',
    description: 'sw-settings-message-stats.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-bars-square',
    favicon: 'icon-module-settings.svg',

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
        icon: 'regular-bars-square',
        privilege: 'system.system_config',
    },
});

export {};
