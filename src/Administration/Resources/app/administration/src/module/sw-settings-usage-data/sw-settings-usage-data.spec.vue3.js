import 'src/module/sw-settings-usage-data';

const { Module, Component } = Shopware;

describe('src/module/sw-settings-usage-data', () => {
    it('should register the module', () => {
        const module = Module.getModuleRegistry().get('sw-settings-usage-data');
        expect(module).toBeTruthy();

        const routes = module.routes;
        expect(routes.size).toBe(1);

        const manifest = module.manifest;
        expect(manifest.name).toBe('usage-data');
    });

    it('should register the component', () => {
        const components = Component.getComponentRegistry();
        expect(components.has('sw-settings-usage-data')).toBe(true);
    });
});
