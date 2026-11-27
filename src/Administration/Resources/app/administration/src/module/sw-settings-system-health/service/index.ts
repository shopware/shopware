/**
 * @sw-package framework
 */
import type { SubContainer } from '../../../global.types';
import SystemHealthApiService from './system-health.api.service';
import SystemHealthCheckRegistry from './system-health-check-registry';

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        systemHealthApiService: SystemHealthApiService;
        systemHealthCheckRegistry: SystemHealthCheckRegistry;
    }
}

/**
 * @private
 */
Shopware.Service().register('systemHealthApiService', () => {
    return new SystemHealthApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

/**
 * @private
 */
Shopware.Service().register('systemHealthCheckRegistry', () => new SystemHealthCheckRegistry());
