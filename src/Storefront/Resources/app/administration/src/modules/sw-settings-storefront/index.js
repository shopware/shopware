/**
 * @sw-package framework
 */

/**
 * @deprecated tag:v6.8.0 - Will be @private
 */
Shopware.Component.register('sw-settings-storefront-index', () => import('./page/sw-settings-storefront-index'));

/**
 * @deprecated tag:v6.8.0 - Will be @private
 */
Shopware.Component.register('sw-settings-storefront-configuration', () => import('./component/sw-settings-storefront-configuration'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-settings-storefront', {
    type: 'core',
    name: 'sw-settings-storefront',
    title: 'sw-settings-storefront.general.mainMenuItemGeneral',
    description: 'sw-settings-storefront.general.description',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-storefront-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.storefront.index',
        icon: 'regular-storefront',
        privilege: 'system.system_config',
    },
});
