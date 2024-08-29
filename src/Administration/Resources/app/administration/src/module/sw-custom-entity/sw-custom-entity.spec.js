/**
 * @package content
 * @group disabledCompat
 */
import './index';

describe('src/module/sw-custom-entity', () => {
    it('should register all custom entity components', async () => {
        const components = Shopware.Component.getComponentRegistry();
        const expectedComponents = [
            'sw-generic-custom-entity-detail',
            'sw-generic-custom-entity-list',
            'sw-custom-entity-input-field',
            'sw-generic-cms-page-assignment',
            'sw-generic-seo-general-card',
            'sw-generic-social-media-card',
        ];

        expect(components.size).toBe(6);
        expectedComponents.forEach((expectedComponent) => {
            expect(components.has(expectedComponent)).toBe(true);
        });
    });

    it('should register the custom entity module correctly', async () => {
        const customEntityModule = Shopware.Module.getModuleRegistry().get('sw-custom-entity');
        const expectedRoutes = [
            'sw.custom.entity.index',
            'sw.custom.entity.detail',
            'sw.custom.entity.create',
        ];

        expect(customEntityModule.routes.size).toBe(3);
        customEntityModule.routes.forEach((route) => {
            expect(expectedRoutes).toContain(route.name);
            expect(route.path).toContain('/sw/custom/entity/:entityName/');
            expect(route.name).toContain('sw.custom.entity.');
            expect(route.type).toBe('plugin');
            expect(route.components).not.toBeNull();
            expect(route.isChildren).not.toBeNull();
            expect(route.routeKey).not.toBeNull();
        });

        const manifest = customEntityModule.manifest;
        expect(manifest.title).toBe('sw-custom-entity.general.mainMenuItemGeneral');
        expect(manifest.type).toBe('plugin');
        expect(manifest.name).toBe('custom-entity');
        expect(manifest.version).not.toBeNull();
        expect(manifest.targetVersion).not.toBeNull();
        expect(manifest.routes).not.toBeNull();
        expect(Object.values(manifest.routes)).toHaveLength(3);
        expect(manifest.display).toBe(true);
    });
});
