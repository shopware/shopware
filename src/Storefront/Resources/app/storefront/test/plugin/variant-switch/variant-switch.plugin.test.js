import VariantSwitchPlugin from 'src/plugin/variant-switch/variant-switch.plugin';
import NativeEventEmitter from 'src/helper/emitter.helper';

describe('VariantSwitchPlugin tests', () => {
    let variantSwitchPlugin = undefined;
    let spyInit = jest.fn();
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        document.$emitter = new NativeEventEmitter();

        window.focusHandler = {
            saveFocusStatePersistent: jest.fn(),
            resumeFocusStatePersistent: jest.fn(),
        };

        // mock variant switch plugins
        const mockElement = document.createElement('form');
        const mockInput = document.createElement('input');

        mockInput.classList.add('product-detail-configurator-option-input');
        mockInput.setAttribute('type', 'radio');
        mockInput.setAttribute('name', 'color');
        mockInput.setAttribute('value', '1');

        mockElement.appendChild(mockInput);
        variantSwitchPlugin = new VariantSwitchPlugin(mockElement);

        // create spy elements
        variantSwitchPlugin.init = spyInit;
        window.PluginManager.initializePlugins = spyInitializePlugins;
    });

    afterEach(() => {
        variantSwitchPlugin = undefined;
        spyInit.mockClear();
        spyInitializePlugins.mockClear();
        window.PluginManager.initializePlugins = undefined;
    });

    test('variant switch plugin exists', () => {
        expect(typeof variantSwitchPlugin).toBe('object');
    });

    test('_onChange get called on click', () => {
        // Mock the function which should be called on click
        variantSwitchPlugin._onChange = jest.fn();
        const spy = jest.spyOn(variantSwitchPlugin, '_onChange');

        // simulate click
        const mockInput = variantSwitchPlugin.el.firstChild;
        mockInput.click();

        expect(spy).toHaveBeenCalled();

        // Reset mock
        variantSwitchPlugin._onChange.mockRestore();
    });

    test('_redirectVariant should get called', () => {
        // Mock the function which should be called on click
        variantSwitchPlugin._redirectToVariant = jest.fn();
        const spy = jest.spyOn(variantSwitchPlugin, '_redirectToVariant');

        // simulate click
        const mockInput = variantSwitchPlugin.el.firstChild;
        mockInput.click();

        expect(spy).toHaveBeenCalled();

        // Reset mock
        variantSwitchPlugin._redirectToVariant.mockRestore();
    });

    test('_redirectVariant should not get called if cms elementId exists', () => {
        variantSwitchPlugin._elementId = '1';

        // Mock the function which should be called on click
        variantSwitchPlugin._redirectToVariant = jest.fn();
        const spy = jest.spyOn(variantSwitchPlugin, '_redirectToVariant');

        // simulate click
        const mockInput = variantSwitchPlugin.el.firstChild;
        mockInput.click();

        expect(spy).not.toHaveBeenCalled();

        // Reset mock
        variantSwitchPlugin._redirectToVariant.mockRestore();
    });

    test('_redirectVariant should not get called if cms elementId exists and page type is not product detail', () => {
        variantSwitchPlugin._elementId = '1';
        variantSwitchPlugin._pageType = 'landingpage';

        // Mock the function which should be called on click
        variantSwitchPlugin._redirectToVariant = jest.fn();
        const spy = jest.spyOn(variantSwitchPlugin, '_redirectToVariant');

        // simulate click
        const mockInput = variantSwitchPlugin.el.firstChild;
        mockInput.click();

        expect(spy).not.toHaveBeenCalled();

        // Reset mock
        variantSwitchPlugin._redirectToVariant.mockRestore();
    });

    test('_redirectVariant should get called if cms elementId exists and page type is product detail', () => {
        variantSwitchPlugin._elementId = '1';
        variantSwitchPlugin._pageType = 'product_detail';

        // Mock the function which should be called on click
        variantSwitchPlugin._redirectToVariant = jest.fn();
        const spy = jest.spyOn(variantSwitchPlugin, '_redirectToVariant');

        // simulate click
        const mockInput = variantSwitchPlugin.el.firstChild;
        mockInput.click();

        expect(spy).toHaveBeenCalled();

        // Reset mock
        variantSwitchPlugin._redirectToVariant.mockRestore();
    });

    test('Ensure the updateBuyWidget event is fired with correct params', () => {
        function cb(event) {
            expect(event.detail.elementId).toEqual('1');
        }

        document.$emitter.subscribe('updateBuyWidget', cb);

        variantSwitchPlugin._elementId = '1';

        // simulate click
        const mockInput = variantSwitchPlugin.el.firstChild;
        mockInput.click();
    });
});
