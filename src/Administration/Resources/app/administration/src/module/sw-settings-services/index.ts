
import type { SubContainer } from '../../global.types';
import ShopwareServicesService from './service/shopware-services.service';

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-services-index',
    () => import('./page/sw-settings-services-index'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-services-hero',
    () => import('./component/sw-settings-services-hero'),
    );

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-services-service-card',
    () => import('./component/sw-settings-services-service-card'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-services-revoke-permissions-modal',
    () => import('./component/sw-settings-services-revoke-permissions-modal'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-services-deactivate-modal',
    () => import('./component/sw-settings-services-deactivate-modal'),
);

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

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        shopwareServicesService: ShopwareServicesService;
    }
}

/**
 * @private
 */
Shopware.Service().register('shopwareServicesService', () => {
    return new ShopwareServicesService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});
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