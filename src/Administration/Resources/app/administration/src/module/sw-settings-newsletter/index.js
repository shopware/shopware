/**
 * @package customer-order
 */

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-newsletter', () => import('./page/sw-settings-newsletter'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-newsletter', {
    type: 'core',
    name: 'settings-newsletter',
    title: 'sw-settings-newsletter.general.mainMenuItemGeneral',
    description: 'sw-settings-newsletter.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-newsletter',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.newsletter.index',
        icon: 'regular-inbox',
        privilege: 'system.system_config',
    },
});
