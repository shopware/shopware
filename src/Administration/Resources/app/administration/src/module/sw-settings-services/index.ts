import './service';
/**
 * @private
 */
Shopware.Component.register('sw-settings-services-index', () => import('./page/sw-settings-services-index'));

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-services-dashboard-banner',
    () => import('./component/sw-settings-services-dashboard-banner'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-services-grant-permissions-modal',
    () => import('./component/sw-settings-services-grant-permissions-modal'),
);

/**
 * @sw-package framework
 * @private
 */
Shopware.Module.register('sw-settings-services', {
    type: 'core',
    name: 'services',
    title: 'sw-settings-services.general.title',
    description: 'sw-settings-services.general.description',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-services-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.plugin_maintain',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.services.index',
        icon: 'regular-view-grid',
        privilege: 'system.plugin_maintain',
    },
});

/**
 * @sw-package framework
 * @private
 */
export {};
