import BuyBoxPlugin from 'src/plugin/buy-box/buy-box.plugin';
import NativeEventEmitter from 'src/helper/emitter.helper';

describe('BuyBoxPlugin tests', () => {
    let buyBoxPlugin = undefined;
    let spyInit = jest.fn();
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        const mockElement = document.createElement('div');

        document.$emitter = new NativeEventEmitter();

        // mock buy box plugins
        buyBoxPlugin = new BuyBoxPlugin(mockElement);

        // create spy elements
        buyBoxPlugin.init = spyInit;
        window.PluginManager.initializePlugins = spyInitializePlugins;
    });

    afterEach(() => {
        buyBoxPlugin = undefined;
        spyInit.mockClear();
        spyInitializePlugins.mockClear();
        window.PluginManager.initializePlugins = undefined;
    });

    test('buy box plugin exists', () => {
        expect(typeof buyBoxPlugin).toBe('object');
    });

    test('_handleUpdateBuyWidget should get called', () => {
        // Mock the function
        buyBoxPlugin._handleUpdateBuyWidget = jest.fn();
        document.$emitter.subscribe('updateBuyWidget',
            buyBoxPlugin._handleUpdateBuyWidget.bind(buyBoxPlugin));

        const spy = jest.spyOn(buyBoxPlugin, '_handleUpdateBuyWidget');

        document.$emitter.publish('updateBuyWidget');

        expect(spy).toHaveBeenCalled();

        // Reset mock
        buyBoxPlugin._handleUpdateBuyWidget.mockRestore();
    });

    test('should reload the buy widget if elementId of event and plugin are identical', () => {
        // Mock the function
        buyBoxPlugin._httpClient.get = jest.fn();

        const spy = jest.spyOn(buyBoxPlugin._httpClient, 'get');

        buyBoxPlugin.options.elementId = '1';

        document.$emitter.subscribe('updateBuyWidget',
            buyBoxPlugin._handleUpdateBuyWidget.bind(buyBoxPlugin));

        document.$emitter.publish('updateBuyWidget', { elementId: '1' });

        expect(spy).toHaveBeenCalled();

        // Reset mock
        buyBoxPlugin._httpClient.get.mockRestore();
    });

    test('should not reload the buy widget if elementId of event and plugin are not identical', () => {
        // Mock the function
        buyBoxPlugin._httpClient.get = jest.fn();

        const spy = jest.spyOn(buyBoxPlugin._httpClient, 'get');

        buyBoxPlugin.options.elementId = '1';

        document.$emitter.subscribe('updateBuyWidget',
            buyBoxPlugin._handleUpdateBuyWidget.bind(buyBoxPlugin));

        document.$emitter.publish('updateBuyWidget', { elementId: '2' });

        expect(spy).not.toHaveBeenCalled();

        // Reset mock
        buyBoxPlugin._httpClient.get.mockRestore();
    });
});
