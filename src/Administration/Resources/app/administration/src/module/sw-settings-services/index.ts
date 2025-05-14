
import type { SubContainer } from '../../global.types';
import ShopwareServicesService from './service/shopware-services.service';

/**
 * @private
 */
Shopware.Component.register('sw-settings-services-index', () => import('./page/sw-settings-services-index'));
Shopware.Component.register('sw-settings-services-hero', () => import('./component/sw-settings-services-hero'));

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
    title: 'Shopware Services',
    description: 'Overview and Settings about installed Shopware Services',
    color: '#9AA8B5',
    icon: 'regular-view-grid',
    favicon: 'icon-module-settings.png',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-services-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.services.index',
        icon: 'regular-view-grid',
    },
});

/**
 * @sw-package framework
 * @private
 */
export {};