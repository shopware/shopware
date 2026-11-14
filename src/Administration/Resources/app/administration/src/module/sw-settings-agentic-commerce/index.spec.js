/**
 * @sw-package discovery
 */
import './index';

const { Module } = Shopware;

describe('src/module/sw-settings-agentic-commerce/index.js', () => {
    it('should register the component', () => {
        expect(Shopware.Component.getComponentRegistry().has('sw-settings-agentic-commerce')).toBeTruthy();
    });

    it('should register the module in the commerce settings group', () => {
        const module = Module.getModuleRegistry().get('sw-settings-agentic-commerce');
        expect(module).toBeDefined();

        expect(module.manifest.name).toBe('settings-agentic-commerce');
        expect(module.manifest.type).toBe('core');

        const settingsItem = module.manifest.settingsItem[0];
        expect(settingsItem.group).toBe('commerce');
        expect(settingsItem.to).toBe('sw.settings.agentic.commerce.index');
        expect(settingsItem.privilege).toBe('system.system_config');
    });

    it('should register the index route', () => {
        const module = Module.getModuleRegistry().get('sw-settings-agentic-commerce');
        const route = module.routes.get('sw.settings.agentic.commerce.index');

        expect(route).toBeDefined();
        expect(route.path).toBe('/sw/settings/agentic/commerce/index');
        expect(route.meta).toEqual({
            parentPath: 'sw.settings.index',
            privilege: 'system.system_config',
        });
    });
});
