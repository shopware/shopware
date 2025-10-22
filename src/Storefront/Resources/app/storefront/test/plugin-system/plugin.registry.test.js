import PluginRegistry from 'src/plugin-system/plugin.registry';

/**
 * @sw-package framework
 */
describe('Plugin Registry', () => {
    let registry;

    beforeEach(() => {
        registry = new PluginRegistry();
    });

    afterEach(() => {
        registry.clear();
    });

    describe('constructor', () => {
        it('should initialize with empty registry', () => {
            expect(registry._registry).toBeInstanceOf(Map);
            expect(registry._registry.size).toBe(0);
        });
    });

    describe('has', () => {
        it('should return false for non-existing plugin without selector', () => {
            expect(registry.has('NonExistingPlugin')).toBe(false);
        });

        it('should return true for existing plugin without selector', () => {
            const pluginClass = class TestPlugin {};
            registry.set('TestPlugin', pluginClass);
            
            expect(registry.has('TestPlugin')).toBe(true);
        });

        it('should return false for non-existing plugin with selector', () => {
            expect(registry.has('NonExistingPlugin', '.test-selector')).toBe(false);
        });

        it('should return false for existing plugin without registrations', () => {
            const pluginClass = class TestPlugin {};
            registry.set('TestPlugin', pluginClass);
            
            expect(registry.has('TestPlugin', '.test-selector')).toBe(false);
        });

        it('should return false for existing plugin with registrations but non-existing selector', () => {
            const pluginClass = class TestPlugin {};
            registry.set('TestPlugin', pluginClass, '.existing-selector');
            
            expect(registry.has('TestPlugin', '.non-existing-selector')).toBe(false);
        });

        it('should return true for existing plugin with existing selector', () => {
            const pluginClass = class TestPlugin {};
            registry.set('TestPlugin', pluginClass, '.test-selector');
            
            expect(registry.has('TestPlugin', '.test-selector')).toBe(true);
        });
    });

    describe('set', () => {
        it('should add a plugin without selector', () => {
            const pluginClass = class TestPlugin {};
            
            const result = registry.set('TestPlugin', pluginClass);
            
            expect(result).toBe(registry);
            expect(registry.has('TestPlugin')).toBe(true);
            
            const pluginMap = registry.get('TestPlugin');
            expect(pluginMap.get('class')).toBe(pluginClass);
            expect(pluginMap.get('name')).toBe('TestPlugin');
            expect(pluginMap.has('async')).toBe(false);
        });

        it('should add a plugin with selector', () => {
            const pluginClass = class TestPlugin {};
            const options = { test: true };
            
            const result = registry.set('TestPlugin', pluginClass, '.test-selector', options);
            
            expect(result).toBe(registry);
            expect(registry.has('TestPlugin', '.test-selector')).toBe(true);
            
            const pluginMap = registry.get('TestPlugin');
            const registrations = pluginMap.get('registrations');
            expect(registrations.get('.test-selector')).toEqual({
                selector: '.test-selector',
                options: options,
            });
        });

        it('should add a plugin with async flag', () => {
            const pluginClass = class TestPlugin {};
            
            registry.set('TestPlugin', pluginClass, null, null, true);
            
            const pluginMap = registry.get('TestPlugin');
            expect(pluginMap.get('async')).toBe(true);
        });

        it('should update existing plugin', () => {
            const pluginClass1 = class TestPlugin1 {};
            const pluginClass2 = class TestPlugin2 {};
            
            registry.set('TestPlugin', pluginClass1);
            registry.set('TestPlugin', pluginClass2);
            
            const pluginMap = registry.get('TestPlugin');
            expect(pluginMap.get('class')).toBe(pluginClass2);
        });

        it('should add multiple registrations for same plugin', () => {
            const pluginClass = class TestPlugin {};
            
            registry.set('TestPlugin', pluginClass, '.selector1');
            registry.set('TestPlugin', pluginClass, '.selector2');
            
            expect(registry.has('TestPlugin', '.selector1')).toBe(true);
            expect(registry.has('TestPlugin', '.selector2')).toBe(true);
            
            const pluginMap = registry.get('TestPlugin');
            const registrations = pluginMap.get('registrations');
            expect(registrations.size).toBe(2);
        });

        it('should initialize instances array', () => {
            const pluginClass = class TestPlugin {};
            
            registry.set('TestPlugin', pluginClass);
            
            const pluginMap = registry.get('TestPlugin');
            expect(pluginMap.has('instances')).toBe(true);
            expect(pluginMap.get('instances')).toEqual([]);
        });
    });

    describe('get', () => {
        it('should return undefined for non-existing plugin', () => {
            expect(registry.get('NonExistingPlugin')).toBeUndefined();
        });

        it('should return plugin map for existing plugin', () => {
            const pluginClass = class TestPlugin {};
            registry.set('TestPlugin', pluginClass);
            
            const pluginMap = registry.get('TestPlugin');
            expect(pluginMap).toBeInstanceOf(Map);
            expect(pluginMap.get('class')).toBe(pluginClass);
            expect(pluginMap.get('name')).toBe('TestPlugin');
        });
    });

    describe('delete', () => {
        it('should delete entire plugin when no selector provided', () => {
            const pluginClass = class TestPlugin {};
            registry.set('TestPlugin', pluginClass, '.selector1');
            registry.set('TestPlugin', pluginClass, '.selector2');
            
            const result = registry.delete('TestPlugin');
            
            expect(result).toBe(true);
            expect(registry.has('TestPlugin')).toBe(false);
        });

        it('should delete specific registration when multiple exist', () => {
            const pluginClass = class TestPlugin {};
            registry.set('TestPlugin', pluginClass, '.selector1');
            registry.set('TestPlugin', pluginClass, '.selector2');
            
            const result = registry.delete('TestPlugin', '.selector1');
            
            expect(result).toBe(registry);
            expect(registry.has('TestPlugin', '.selector1')).toBe(false);
            expect(registry.has('TestPlugin', '.selector2')).toBe(true);
            expect(registry.has('TestPlugin')).toBe(true);
        });

        it('should delete entire plugin when no selector is given', () => {
            const pluginClass = class TestPlugin {};
            registry.set('TestPlugin', pluginClass, '.selector1');

            const result = registry.delete('TestPlugin', null);

            expect(result).toBe(true);
            expect(registry.has('TestPlugin')).toBe(false);
        });

        it('should return true when plugin does not exist', () => {
            const result = registry.delete('NonExistingPlugin', '.selector');
            expect(result).toBe(true);
        });

        it('should not remove other registrations when given selector has no registration', () => {
            const pluginClass = class TestPlugin {};
            registry.set('TestPlugin', pluginClass, '.selector1');
            
            const result = registry.delete('TestPlugin', '.selector2');

            expect(registry.get('TestPlugin').get('registrations').has('.selector1')).toBe(true);
            expect(registry.get('TestPlugin').get('registrations').has('.selector2')).toBe(false);
            expect(result).toBe(registry);
        });
    });

    describe('clear', () => {
        it('should clear all plugins from registry', () => {
            const pluginClass1 = class TestPlugin1 {};
            const pluginClass2 = class TestPlugin2 {};
            
            registry.set('TestPlugin1', pluginClass1, '.selector1');
            registry.set('TestPlugin2', pluginClass2, '.selector2');
            
            expect(registry._registry.size).toBe(2);
            
            const result = registry.clear();
            
            expect(result).toBe(registry);
            expect(registry._registry.size).toBe(0);
        });
    });

    describe('keys', () => {
        it('should return empty object for empty registry', () => {
            const keys = registry.keys();
            expect(keys).toEqual({});
        });

        it('should return all plugin names as object', () => {
            const pluginClass1 = class TestPlugin1 {};
            const pluginClass2 = class TestPlugin2 {};
            
            registry.set('TestPlugin1', pluginClass1);
            registry.set('TestPlugin2', pluginClass2);
            
            const keys = registry.keys();
            
            expect(keys).toHaveProperty('TestPlugin1');
            expect(keys).toHaveProperty('TestPlugin2');
            expect(keys.TestPlugin1).toBeInstanceOf(Map);
            expect(keys.TestPlugin2).toBeInstanceOf(Map);
        });
    });

    describe('integration tests', () => {
        it('should handle complex plugin lifecycle', () => {
            const pluginClass = class ComplexPlugin {};
            
            // Add plugin with multiple registrations
            registry.set('ComplexPlugin', pluginClass, '.selector1', { option1: true });
            registry.set('ComplexPlugin', pluginClass, '.selector2', { option2: true });
            
            // Verify registrations
            expect(registry.has('ComplexPlugin', '.selector1')).toBe(true);
            expect(registry.has('ComplexPlugin', '.selector2')).toBe(true);
            
            // Get plugin data
            const pluginMap = registry.get('ComplexPlugin');
            expect(pluginMap.get('class')).toBe(pluginClass);
            expect(pluginMap.get('name')).toBe('ComplexPlugin');
            
            // Delete one registration
            registry.delete('ComplexPlugin', '.selector1');
            expect(registry.has('ComplexPlugin', '.selector1')).toBe(false);
            expect(registry.has('ComplexPlugin', '.selector2')).toBe(true);
            
            // Delete remaining registration
            registry.delete('ComplexPlugin', '.selector2');
            expect(registry.get('ComplexPlugin').get('registrations').has('.selector2')).toBe(false);
        });

        it('should handle async plugins correctly', () => {
            const pluginClass = class AsyncPlugin {};
            
            registry.set('AsyncPlugin', pluginClass, '.selector', {}, true);
            
            const pluginMap = registry.get('AsyncPlugin');
            expect(pluginMap.get('async')).toBe(true);
            expect(pluginMap.get('class')).toBe(pluginClass);
        });

        it('should handle plugins without options', () => {
            const pluginClass = class SimplePlugin {};
            
            registry.set('SimplePlugin', pluginClass, '.selector');
            
            const pluginMap = registry.get('SimplePlugin');
            const registrations = pluginMap.get('registrations');
            expect(registrations.get('.selector')).toEqual({
                selector: '.selector',
                options: undefined,
            });
        });
    });
});
