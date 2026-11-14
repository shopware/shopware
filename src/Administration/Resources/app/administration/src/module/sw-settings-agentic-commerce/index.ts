/**
 * @sw-package discovery
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-agentic-commerce', () => import('./page/sw-settings-agentic-commerce'));

Shopware.Module.register('sw-settings-agentic-commerce', {
    type: 'core',
    name: 'settings-agentic-commerce',
    title: 'sw-settings-agentic-commerce.general.mainMenuItemGeneral',
    description: 'sw-settings-agentic-commerce.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-sparkle',
    favicon: 'icon-module-settings.svg',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-agentic-commerce',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'commerce',
        to: 'sw.settings.agentic.commerce.index',
        icon: 'regular-sparkle',
        privilege: 'system.system_config',
    },
});

export {};
