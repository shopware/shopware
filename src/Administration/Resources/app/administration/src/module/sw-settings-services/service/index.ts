/**
 * @sw-package framework
 */
import type { SubContainer } from '../../../global.types';
import ShopwareServicesService from './shopware-services.service';

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
        Shopware.Service('systemConfigApiService'),
    );
});
