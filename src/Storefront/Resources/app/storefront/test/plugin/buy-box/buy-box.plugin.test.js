import BuyBoxPlugin from 'src/plugin/buy-box/buy-box.plugin';
import NativeEventEmitter from 'src/helper/emitter.helper';

describe('BuyBoxPlugin tests', () => {
    let buyBoxPlugin = undefined;
    let spyInit = jest.fn();
    let spyInitializePlugins = jest.fn();
    let originalFetch;

    beforeEach(() => {
        originalFetch = global.fetch;
        global.fetch = jest.fn(() => Promise.resolve({ text: () => Promise.resolve('') }));
        const mockElement = document.createElement('div');

        document.$emitter = new NativeEventEmitter();

        // mock buy box plugins
        buyBoxPlugin = new BuyBoxPlugin(mockElement);

        // create spy elements
        buyBoxPlugin.init = spyInit;
        window.PluginManager.initializePlugins = spyInitializePlugins;
    });

    afterEach(() => {
        global.fetch = originalFetch;
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

    test('should reload the buy widget if elementId of event and plugin are identical', async () => {
        buyBoxPlugin.options.elementId = '1';

        document.$emitter.subscribe('updateBuyWidget',
            buyBoxPlugin._handleUpdateBuyWidget.bind(buyBoxPlugin));

        document.$emitter.publish('updateBuyWidget', { elementId: '1', url: '/buy-widget' });
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalledWith('/buy-widget', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        expect(spyInitializePlugins).toHaveBeenCalled();
    });

    test('should not reload the buy widget if elementId of event and plugin are not identical', () => {
        buyBoxPlugin.options.elementId = '1';

        document.$emitter.subscribe('updateBuyWidget',
            buyBoxPlugin._handleUpdateBuyWidget.bind(buyBoxPlugin));

        document.$emitter.publish('updateBuyWidget', { elementId: '2' });

        expect(global.fetch).not.toHaveBeenCalled();
    });
});
