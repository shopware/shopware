/**
 * @sw-package framework
 */
import SystemHealthCheckRegistry from './system-health-check-registry';

describe('module/sw-settings-system-health/service/system-health-check-registry', () => {
    it('should return an empty view for a check without an entry', () => {
        expect(new SystemHealthCheckRegistry().get('MyCheck')).toEqual({});
    });

    it('should merge a later registration into the existing view', () => {
        const registry = new SystemHealthCheckRegistry();

        registry.register('MyCheck', { resolveRoute: 'sw.settings.cache.index' });
        registry.register('MyCheck', { component: 'my-check-result' });

        expect(registry.get('MyCheck')).toEqual({ component: 'my-check-result', resolveRoute: 'sw.settings.cache.index' });
    });
});
