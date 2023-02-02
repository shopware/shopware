import PluginManager from 'src/plugin-system/plugin.manager';
import Plugin from 'src/plugin-system/plugin.class';
import Iterator from "../../src/helper/iterator.helper";

class FooPluginClass extends Plugin {
    init() {}
}

/**
 * @package storefront
 */
describe('Plugin manager', () => {
    beforeEach(() => {
        document.body.innerHTML = '<div data-plugin="true" class="test-class"></div><div id="test-id"></div>';
        jest.spyOn(console, 'error').mockImplementation();

    });

    afterEach(() => {
        expect(console.error).not.toHaveBeenCalled();
    });

    it('should not fail for non-existing id', () => {
        PluginManager.register('FooPlugin', FooPluginClass, '#nonExistingId');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', '#nonExistingId');
    });

    it('should not fail for non-existing HTML tag', () => {
        PluginManager.register('FooPlugin', FooPluginClass, 'nonExistingHtmlTag');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', 'nonExistingHtmlTag');
    });

    it('should not fail for non-existing class', () => {
        PluginManager.register('FooPlugin', FooPluginClass, '.non-existing-class');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', '.non-existing-class');
    });

    it('should not fail for non-existing selector', () => {
        PluginManager.register('FooPlugin', FooPluginClass, '[data-non-existing-data-attribute]');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', '[data-non-existing-data-attribute]');
    });

    it('should initialize plugin with class selector', () => {
        PluginManager.register('FooPlugin', FooPluginClass, '.test-class');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('FooPlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPlugin', '.test-class');
    });

    it('should initialize plugin with id selector', () => {
        PluginManager.register('FooPluginID', FooPluginClass, '#test-id');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginID').length).toBe(1);
        expect(PluginManager.getPluginInstances('FooPluginID')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginID', '#test-id');
    });

    it('should initialize plugin with tag selector', () => {
        PluginManager.register('FooPluginTag', FooPluginClass, 'div');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginTag').length).toBe(2);

        Iterator.iterate(PluginManager.getPluginInstances('FooPluginTag'), (instance) => {
            expect(instance._initialized).toBe(true);
        });

        PluginManager.deregister('FooPluginTag', 'div');
    });

    it('should initialize plugin with data-attribute selector', () => {
        PluginManager.register('FooPluginDataAttr', FooPluginClass, '[data-plugin]');

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginDataAttr').length).toBe(1);

        expect(PluginManager.getPluginInstances('FooPluginDataAttr')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginDataAttr', '[data-plugin]');
    });

    it('should initialize plugin with mixed selector (class and data-attribute)', () => {
        const selector = '.test-class[data-plugin]';
        PluginManager.register('FooPluginClassDataAttr', FooPluginClass, selector);

        PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginClassDataAttr').length).toBe(1);

        expect(PluginManager.getPluginInstances('FooPluginClassDataAttr')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginClassDataAttr', selector);
    });
});
