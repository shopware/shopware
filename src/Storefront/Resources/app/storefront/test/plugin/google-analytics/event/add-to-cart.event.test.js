import NativeEventEmitter from 'src/helper/emitter.helper';
import AddToCartEvent from 'src/plugin/google-analytics/events/add-to-cart.event';

/**
 * Creates a mock plugin instance with a real NativeEventEmitter.
 * This allows testing event subscriptions without instantiating the full plugin.
 */
function createMockPluginInstance() {
    const element = document.createElement('div');
    return {
        el: element,
        $emitter: new NativeEventEmitter(element),
    };
}

describe('plugin/google-analytics/events/add-to-cart.event', () => {
    let addToCartInstances = [];
    let listingInstances = [];

    beforeEach(() => {
        addToCartInstances = [];
        listingInstances = [];

        window.gtag = jest.fn();
        window.currencyIsoCode = 'EUR';

        // Mock PluginManager.getPluginInstances to return our test instances
        window.PluginManager.getPluginInstances = jest.fn((name) => {
            if (name === 'AddToCart') {
                return addToCartInstances;
            }
            if (name === 'Listing') {
                return listingInstances;
            }
            throw new Error(`Plugin ${name} not found`);
        });

        window.PluginManager.initializePlugins = jest.fn().mockResolvedValue();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true for all routes', () => {
        const event = new AddToCartEvent();
        expect(event.supports('', '', 'frontend.detail.page')).toBe(true);
        expect(event.supports('', '', 'frontend.navigation.page')).toBe(true);
    });

    test('returns correct plugin name and events', () => {
        const event = new AddToCartEvent();
        expect(event.getPluginName()).toBe('AddToCart');
        expect(event.getEvents()).toHaveProperty('beforeFormSubmit');
    });

    test('fires add_to_cart event when AddToCart plugin emits beforeFormSubmit', () => {
        document.body.innerHTML = `
            <meta itemprop="priceCurrency" content="EUR">
            <meta itemprop="price" content="99.99">
        `;

        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        const event = new AddToCartEvent();
        event.execute();

        // Simulate form submission via plugin event
        const formData = new FormData();
        formData.append('lineItems[product-123][id]', 'product-123');
        formData.append('product-name', 'Test Product');
        formData.append('lineItems[product-123][quantity]', '2');
        formData.append('brand-name', 'Test Brand');

        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', expect.objectContaining({
            'currency': 'EUR',
            'items': expect.arrayContaining([
                expect.objectContaining({
                    'id': 'product-123',
                    'name': 'Test Product',
                    'quantity': '2',
                    'brand': 'Test Brand',
                }),
            ]),
        }));
    });

    test('does not fire event when AddToCart plugin is not present', () => {
        // addToCartInstances is empty
        const event = new AddToCartEvent();
        event.execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not duplicate subscriptions when execute is called multiple times', () => {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        const event = new AddToCartEvent();
        event.execute();
        event.execute(); // Call again

        // Trigger event
        const formData = new FormData();
        formData.append('lineItems[product-123][id]', 'product-123');
        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        // Should only fire once, not twice
        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    test('subscribes to Listing plugin afterRenderResponse for pagination support', () => {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        const listingInstance = createMockPluginInstance();
        listingInstances.push(listingInstance);

        const subscribeSpy = jest.spyOn(listingInstance.$emitter, 'subscribe');

        const event = new AddToCartEvent();
        event.execute();

        // Verify it subscribed to the Listing plugin's afterRenderResponse event
        expect(subscribeSpy).toHaveBeenCalledWith(
            'Listing/afterRenderResponse',
            expect.any(Function)
        );
    });

    test('re-subscribes to new AddToCart instances after listing pagination', async () => {
        // Initial AddToCart instance
        const initialAddToCartInstance = createMockPluginInstance();
        addToCartInstances.push(initialAddToCartInstance);

        // Listing instance
        const listingInstance = createMockPluginInstance();
        listingInstances.push(listingInstance);

        const event = new AddToCartEvent();
        event.execute();

        // Verify initial AddToCart works
        const formData = new FormData();
        formData.append('lineItems[product-1][id]', 'product-1');
        initialAddToCartInstance.$emitter.publish('beforeFormSubmit', formData);
        expect(window.gtag).toHaveBeenCalledTimes(1);

        // Simulate pagination: new AddToCart instance appears
        const newAddToCartInstance = createMockPluginInstance();
        addToCartInstances.push(newAddToCartInstance);

        // Trigger the afterRenderResponse event
        listingInstance.$emitter.publish('Listing/afterRenderResponse', { response: {} });

        // Wait for async handler (which awaits initializePlugins)
        await Promise.resolve();

        // Verify initializePlugins was awaited
        expect(window.PluginManager.initializePlugins).toHaveBeenCalled();

        // Trigger event on the new instance - should work now
        const newFormData = new FormData();
        newFormData.append('lineItems[product-new][id]', 'product-new');
        newAddToCartInstance.$emitter.publish('beforeFormSubmit', newFormData);

        expect(window.gtag).toHaveBeenCalledTimes(2);
        expect(window.gtag).toHaveBeenLastCalledWith('event', 'add_to_cart', expect.objectContaining({
            'items': expect.arrayContaining([
                expect.objectContaining({ 'id': 'product-new' }),
            ]),
        }));
    });

    test('does not duplicate events on already-subscribed instances after pagination', async () => {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        const listingInstance = createMockPluginInstance();
        listingInstances.push(listingInstance);

        const event = new AddToCartEvent();
        event.execute();

        // Trigger pagination
        listingInstance.$emitter.publish('Listing/afterRenderResponse', { response: {} });
        await Promise.resolve();

        // Trigger add to cart - should fire only once
        const formData = new FormData();
        formData.append('lineItems[product-1][id]', 'product-1');
        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    test('skips Listing subscription when Listing plugin is not present', () => {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        // No Listing instances

        const event = new AddToCartEvent();
        event.execute();

        // Should not throw and should still subscribe to AddToCart
        const formData = new FormData();
        formData.append('lineItems[product-1][id]', 'product-1');
        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });
});
