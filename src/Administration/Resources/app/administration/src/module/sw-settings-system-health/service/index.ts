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

const registry = new SystemHealthCheckRegistry();

/**
 * @private
 */
Shopware.Service().register('systemHealthCheckRegistry', () => registry);

registry.register('AppUrlVerification', { component: 'sw-settings-system-health-app-url' });
registry.register('AdministrationReadiness', {
    component: 'sw-settings-system-health-administration',
    resolveRoute: 'sw.settings.cache.index',
});
['SalesChannelsReadiness', 'ProductDetailReadiness', 'ProductListingReadiness'].forEach((name) => {
    registry.register(name, {
        component: 'sw-settings-system-health-storefront-pages',
        resolveRoute: 'sw.settings.cache.index',
    });
});
registry.register('LocalesReadiness', {
    component: 'sw-settings-system-health-locales',
    resolveRoute: 'sw.settings.language.index',
});
