/**
 * @sw-package framework
 */
import type { SubContainer } from '../../../global.types';
import ShopwareServicesService from './shopware-services.service';
import ServiceRegistryClient from './service-registry-client';

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        shopwareServicesService: ShopwareServicesService;
        serviceRegistryClient: ServiceRegistryClient;
    }
}

/**
 * @private
 */
Shopware.Service().register('shopwareServicesService', () => {
    return new ShopwareServicesService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
        Shopware.Service('systemConfigApiService'),
    );
});

/**
 * @private
 */
Shopware.Service().register('serviceRegistryClient', () => {
    return new ServiceRegistryClient(Shopware.Context.api.serviceRegistryUrl!);
});
