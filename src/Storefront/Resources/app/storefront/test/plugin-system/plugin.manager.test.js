import PluginManager from 'src/plugin-system/plugin.manager';
import Plugin from 'src/plugin-system/plugin.class';
import Iterator from 'src/helper/iterator.helper';

class FooPluginClass extends Plugin {
    init() {}
}

class AsyncPluginClass extends Plugin {
    init() {}
}

class SinglePlugin extends Plugin {
    init() {}
}

class AsyncPluginClassWithMethods extends Plugin {
    init() {}

    static sayHello() {
        return 'Hello';
    }
}

class CoreCartPluginClass extends Plugin {
    init() {}

    getQuantity() {
        return '15,00 EUR';
    }
}

class OverrideCartPluginClass extends CoreCartPluginClass {
    getQuantity() {
        return '79,89 EUR';
    }
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
        jest.resetAllMocks();
        expect(console.error).not.toHaveBeenCalled();
    });

    it('should not fail for non-existing id', async () => {
        PluginManager.register('FooPlugin', FooPluginClass, '#nonExistingId');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', '#nonExistingId');
    });

    it('should not fail for non-existing HTML tag', async () => {
        PluginManager.register('FooPlugin', FooPluginClass, 'nonExistingHtmlTag');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', 'nonExistingHtmlTag');
    });

    it('should not fail for non-existing class', async () => {
        PluginManager.register('FooPlugin', FooPluginClass, '.non-existing-class');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', '.non-existing-class');
    });

    it('should not fail for non-existing selector', async () => {
        PluginManager.register('FooPlugin', FooPluginClass, '[data-non-existing-data-attribute]');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', '[data-non-existing-data-attribute]');
    });

    it('should initialize plugin with class selector', async () => {
        PluginManager.register('FooPlugin', FooPluginClass, '.test-class');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('FooPlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPlugin', '.test-class');
    });

    it('should initialize plugin with id selector', async () => {
        PluginManager.register('FooPluginID', FooPluginClass, '#test-id');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginID').length).toBe(1);
        expect(PluginManager.getPluginInstances('FooPluginID')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginID', '#test-id');
    });

    it('should initialize plugin with tag selector', async () => {
        PluginManager.register('FooPluginTag', FooPluginClass, 'div');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginTag').length).toBe(2);

        Iterator.iterate(PluginManager.getPluginInstances('FooPluginTag'), (instance) => {
            expect(instance._initialized).toBe(true);
        });

        PluginManager.deregister('FooPluginTag', 'div');
    });

    it('should initialize plugin with data-attribute selector', async () => {
        PluginManager.register('FooPluginDataAttr', FooPluginClass, '[data-plugin]');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginDataAttr').length).toBe(1);

        expect(PluginManager.getPluginInstances('FooPluginDataAttr')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginDataAttr', '[data-plugin]');
    });

    it('should initialize plugin with mixed selector (class and data-attribute)', async () => {
        const selector = '.test-class[data-plugin]';
        PluginManager.register('FooPluginClassDataAttr', FooPluginClass, selector);

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginClassDataAttr').length).toBe(1);

        expect(PluginManager.getPluginInstances('FooPluginClassDataAttr')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginClassDataAttr', selector);
    });

    it('should initialize plugin with async import', async () => {
        const asyncImport = new Promise((resolve) => {
            resolve({ default: AsyncPluginClass });
        });

        PluginManager.register('AsyncTest', () => asyncImport, '.test-class');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('AsyncTest').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncTest')[0]._initialized).toBe(true);

        PluginManager.deregister('AsyncTest', '.test-class');
    });

    it('should initialize plugin with async import on DOM element', async () => {
        const asyncImport = new Promise((resolve) => {
            resolve({ default: AsyncPluginClass });
        });

        const element = document.querySelector('.test-class');

        PluginManager.register('AsyncWithElement', () => asyncImport, element);

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('AsyncWithElement').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncWithElement')[0]._initialized).toBe(true);

        PluginManager.deregister('AsyncWithElement', element);
    });

    it('should initialize multiple plugins with async import', async () => {
        const asyncImport1 = new Promise((resolve) => {
            resolve({ default: AsyncPluginClass });
        });

        const asyncImport2 = new Promise((resolve) => {
            resolve({ default: AsyncPluginClassWithMethods });
        });

        PluginManager.register('Async1', () => asyncImport1, '.test-class');
        PluginManager.register('Async2', () => asyncImport2, '#test-id');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('Async1').length).toBe(1);
        expect(PluginManager.getPluginInstances('Async1')[0]._initialized).toBe(true);

        expect(PluginManager.getPluginInstances('Async2').length).toBe(1);
        expect(PluginManager.getPluginInstances('Async2')[0]._initialized).toBe(true);

        PluginManager.deregister('Async1', '.test-class');
        PluginManager.deregister('Async2', '#test-id');
    });

    it('should initialize plugins in correct order, regardless if they are async', async () => {
        document.body.innerHTML = `
            <div data-async-one="true"></div>
            <div data-async-two="true"></div>
            <div data-sync-plugin="true"></div>
        `;

        const spyInit1 = jest.spyOn(AsyncPluginClass.prototype, 'init');
        const spyInit2 = jest.spyOn(FooPluginClass.prototype, 'init');
        const spyInit3 = jest.spyOn(AsyncPluginClassWithMethods.prototype, 'init');

        const asyncImport1 = new Promise((resolve) => {
            resolve({ default: AsyncPluginClass });
        });

        const asyncImport2 = new Promise((resolve) => {
            resolve({ default: AsyncPluginClassWithMethods });
        });

        PluginManager.register('Plugin1', () => asyncImport1, '[data-async-one]');
        PluginManager.register('Plugin2', FooPluginClass, '[data-sync-plugin]');
        PluginManager.register('Plugin3', () => asyncImport2, '[data-async-two]');

        await PluginManager.initializePlugins();

        // Ensure all init methods are called
        expect(spyInit1).toHaveBeenCalledTimes(1);
        expect(spyInit2).toHaveBeenCalledTimes(1);
        expect(spyInit3).toHaveBeenCalledTimes(1);

        // Ensure plugins are initialized in correct order
        expect(spyInit1.mock.invocationCallOrder[0]).toBe(1);
        expect(spyInit2.mock.invocationCallOrder[0]).toBe(2);
        expect(spyInit3.mock.invocationCallOrder[0]).toBe(3);

        PluginManager.deregister('Plugin1', '[data-async-one]');
        PluginManager.deregister('Plugin2', '[data-sync-plugin]');
        PluginManager.deregister('Plugin3', '[data-async-two]');
    });

    it('should be able get plugin instance from element', async () => {
        document.body.innerHTML = `
            <div data-shopping-cart="true"></div>
        `;

        PluginManager.register('ShoppingCart', CoreCartPluginClass, '[data-shopping-cart]');
        await PluginManager.initializePlugins();

        const element = document.querySelector('[data-shopping-cart]');
        const coreCartPluginInstance = PluginManager.getPluginInstanceFromElement(element, 'ShoppingCart');

        expect(PluginManager.getPluginInstances('ShoppingCart').length).toBe(1);
        expect(coreCartPluginInstance.getQuantity()).toBe('15,00 EUR');

        PluginManager.deregister('ShoppingCart', '[data-shopping-cart]');
    });

    it('should be able to override sync plugin', async () => {
        document.body.innerHTML = `
            <div data-cart="true"></div>
        `;

        // Shopware core registers plugin
        PluginManager.register('CoreCart', CoreCartPluginClass, '[data-cart]');

        // App/plugin attempts to override core plugin
        PluginManager.override('CoreCart', OverrideCartPluginClass, '[data-cart]');

        await PluginManager.initializePlugins();

        const element = document.querySelector('[data-cart]');
        const cartPluginInstance = PluginManager.getPluginInstanceFromElement(element, 'CoreCart');

        expect(PluginManager.getPluginInstances('CoreCart').length).toBe(1);
        expect(cartPluginInstance.getQuantity()).toBe('79,89 EUR');

        PluginManager.deregister('CoreCart', '[data-cart]');
    });

    it('should be able to override async plugin', async () => {
        jest.useFakeTimers();

        document.body.innerHTML = `
            <div data-async-cart="true"></div>
        `;

        const asyncCoreCartImport = new Promise((resolve) => {
            // Simulate slower async import
            setTimeout(() => {
                resolve({ default: CoreCartPluginClass });
            }, 100);
        });

        const asyncOverrideCartImport = new Promise((resolve) => {
            // Simulate slower async import
            setTimeout(() => {
                resolve({ default: OverrideCartPluginClass });
            }, 150);
        });

        // Shopware core registers async plugin
        PluginManager.register('AsyncCoreCart', () => asyncCoreCartImport, '[data-async-cart]');

        // App/plugin attempts to override async core plugin
        PluginManager.override('AsyncCoreCart', () => asyncOverrideCartImport, '[data-async-cart]');

        PluginManager.initializePlugins();
        jest.advanceTimersByTime(250);
        await new Promise(process.nextTick);

        const element = document.querySelector('[data-async-cart]');
        const cartPluginInstance = PluginManager.getPluginInstanceFromElement(element, 'AsyncCoreCart');

        expect(PluginManager.getPluginInstances('AsyncCoreCart').length).toBe(1);
        expect(cartPluginInstance.getQuantity()).toBe('79,89 EUR');

        PluginManager.deregister('AsyncCoreCart', '[data-async-cart]');
        jest.useRealTimers();
    });

    it('should initialize single sync plugin on string selector', async () => {
        document.body.innerHTML = `
            <div data-single="true"></div>
        `;

        PluginManager.register('SinglePlugin', SinglePlugin, '[data-single]');

        await PluginManager.initializePlugin('SinglePlugin', '[data-single]', {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('SinglePlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('SinglePlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('SinglePlugin', '[data-single]');
    });

    it('should initialize single sync plugin on DOM node', async () => {
        document.body.innerHTML = `
            <div data-single="true"></div>
        `;
        const element = document.querySelector('[data-single]');

        PluginManager.register('SingleDomPlugin', SinglePlugin, element);

        await PluginManager.initializePlugin('SingleDomPlugin', element, {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('SingleDomPlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('SingleDomPlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('SingleDomPlugin', element);
    });

    it('should initialize single async plugin on string selector', async () => {
        document.body.innerHTML = `
            <div data-async-single="true"></div>
        `;

        const asyncImport = new Promise((resolve) => {
            resolve({ default: SinglePlugin });
        });

        PluginManager.register('AsyncSinglePlugin', () => asyncImport, '[data-async-single]');

        await PluginManager.initializePlugin('AsyncSinglePlugin', '[data-async-single]', {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('AsyncSinglePlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncSinglePlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('AsyncSinglePlugin', '[data-async-single]');
    });

    it('should initialize single async plugin on DOM node', async () => {
        document.body.innerHTML = `
            <div data-async-single="true"></div>
        `;

        const element = document.querySelector('[data-async-single]');

        const asyncImport = new Promise((resolve) => {
            resolve({ default: SinglePlugin });
        });

        PluginManager.register('AsyncSingleDomPlugin', () => asyncImport, element);

        await PluginManager.initializePlugin('AsyncSingleDomPlugin', element, {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('AsyncSingleDomPlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncSingleDomPlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('AsyncSingleDomPlugin', element);
    });

    it('should not initialize single async plugin when selector is not found in the DOM', async () => {
        document.body.innerHTML = `
            <div class="i-am-not-the-plugin-selector"></div>
        `;

        const asyncImport = new Promise((resolve) => {
            resolve({ default: SinglePlugin });
        });

        PluginManager.register('AsyncPluginWithoutFoundSelector', () => asyncImport, '[data-async-single]');

        await PluginManager.initializePlugin('AsyncPluginWithoutFoundSelector', '[data-async-single]', {});

        await new Promise(process.nextTick);

        // No instance is found because the selector is not in the DOM
        expect(PluginManager.getPluginInstances('AsyncPluginWithoutFoundSelector').length).toBe(0);

        PluginManager.deregister('AsyncPluginWithoutFoundSelector', '[data-async-single]');
    });

    it('should initialize single async plugin on selector that differs from original register selector', async () => {
        document.body.innerHTML = `
            <div class="different-selector"></div>
        `;

        const asyncImport = new Promise((resolve) => {
            resolve({ default: SinglePlugin });
        });

        // Plugin is registered with selector '[data-async-single]'
        PluginManager.register('AsyncDifferentSelectorPlugin', () => asyncImport, '[data-async-single]');

        // Plugin is then initialized with selector '.different-selector'
        await PluginManager.initializePlugin('AsyncDifferentSelectorPlugin', '.different-selector', {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('AsyncDifferentSelectorPlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncDifferentSelectorPlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('AsyncDifferentSelectorPlugin', '[data-async-single]');
    });

    it('should be able to modify the options when initializing a single async plugin', async () => {
        document.body.innerHTML = `
            <div data-async-single="true"></div>
        `;

        const asyncImport = new Promise((resolve) => {
            resolve({ default: SinglePlugin });
        });

        PluginManager.register('AsyncPluginWithOpts', () => asyncImport, '[data-async-single]', {
            displayText: 'The initial display text',
        });

        await PluginManager.initializePlugin('AsyncPluginWithOpts', '[data-async-single]', {
            displayText: 'A different display text',
            newOption: 'A new option',
        });

        await new Promise(process.nextTick);

        // Verify that the options were correctly set
        expect(PluginManager.getPluginInstances('AsyncPluginWithOpts')[0].options).toEqual({
            displayText: 'A different display text',
            newOption: 'A new option',
        });

        PluginManager.deregister('AsyncPluginWithOpts', '[data-async-single]');
    });

    it('should show console error when plugin initialization fails', async () => {
        document.body.innerHTML = `
            <div data-async-single-with-error="true"></div>
        `;

        // Cause some trouble by returning a non-class
        const asyncImport = new Promise((resolve) => {
            resolve({ default: 'NOT_A_CLASS' });
        });

        PluginManager.register('AsyncErrorPlugin', () => asyncImport, '[data-async-single-with-error]', {});

        await PluginManager.initializePlugin('AsyncErrorPlugin', '[data-async-single-with-error]', {});

        await new Promise(process.nextTick);

        expect(console.error).toHaveBeenCalled();
        expect(console.error.mock.calls[0][0].message).toContain('The passed plugin is not a function or a class.');

        expect(PluginManager.getPluginInstances('AsyncErrorPlugin').length).toBe(0);

        PluginManager.deregister('AsyncErrorPlugin', '[data-async-single-with-error]');
    });
});
