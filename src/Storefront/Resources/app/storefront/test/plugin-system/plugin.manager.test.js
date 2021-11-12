/**
 * @jest-environment jsdom
 */
import PluginManager from 'src/plugin-system/plugin.manager';
import Plugin from 'src/plugin-system/plugin.class';
import Iterator from "../../src/helper/iterator.helper";

class FooPlugin extends Plugin {
    init() {}
}

beforeEach(() => {
    document.body.innerHTML = '<div data-plugin="true" class="test-class"></div><div id="test-id"></div>';
});

describe('Plugin manager', () => {
    it('should initialize plugin with class selector', () => {
        PluginManager.register('FooPlugin', FooPlugin, '.test-class');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('FooPlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPlugin', '.test-class');
    });

    it('should initialize plugin with id selector', () => {
        PluginManager.register('FooPluginID', FooPlugin, '#test-id');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginID').length).toBe(1);
        expect(PluginManager.getPluginInstances('FooPluginID')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginID', '#test-id');
    });

    it('should initialize plugin with tag selector', () => {
        PluginManager.register('FooPluginTag', FooPlugin, 'div');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginTag').length).toBe(2);

        Iterator.iterate(PluginManager.getPluginInstances('FooPluginTag'), (instance) => {
            expect(instance._initialized).toBe(true);
        });

        PluginManager.deregister('FooPluginTag', 'div');
    });

    it('should initialize plugin with data-attribute selector', () => {
        PluginManager.register('FooPluginDataAttr', FooPlugin, '[data-plugin]');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginDataAttr').length).toBe(1);

        expect(PluginManager.getPluginInstances('FooPluginDataAttr')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginDataAttr', '[data-plugin]');
    });

    it('should initialize plugin with mixed selector (class and data-attribute)', () => {
        const selector = '.test-class[data-plugin]';
        PluginManager.register('FooPluginClassDataAttr', FooPlugin, selector);

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginClassDataAttr').length).toBe(1);

        expect(PluginManager.getPluginInstances('FooPluginClassDataAttr')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginClassDataAttr', selector);
    });
});
