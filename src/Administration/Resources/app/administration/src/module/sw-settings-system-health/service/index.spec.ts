/**
 * @sw-package framework
 */
import './index';
import SystemHealthApiService from './system-health.api.service';
import SystemHealthCheckRegistry from './system-health-check-registry';

describe('module/sw-settings-system-health/service', () => {
    it('should register the API service and the check registry', () => {
        expect(Shopware.Service('systemHealthApiService')).toBeInstanceOf(SystemHealthApiService);
        expect(Shopware.Service('systemHealthCheckRegistry')).toBeInstanceOf(SystemHealthCheckRegistry);
    });
});
