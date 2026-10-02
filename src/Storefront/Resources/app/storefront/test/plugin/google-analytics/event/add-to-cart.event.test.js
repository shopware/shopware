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
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="99.99">
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
            'value': 199.98,
            'items': expect.arrayContaining([
                expect.objectContaining({
                    'item_id': 'product-123',
                    'item_name': 'Test Product',
                    'quantity': 2,
                    'price': 99.99,
                    'item_brand': 'Test Brand',
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
            expect.any(Function),
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
                expect.objectContaining({ 'item_id': 'product-new' }),
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

    test.each([
        ['1', 10, 10],
        ['10', 10, 100],
        ['11', 8, 88],
        ['50', 5, 250],
    ])('reports the graduated price for a quantity of %s', (quantity, price, value) => {
        // the meta tag carries the cheapest tier, which only applies from 50 units on
        document.body.innerHTML = `
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="5">
            <div class="product-detail-buy"
                 data-product-prices='[{"quantity":10,"price":10},{"quantity":49,"price":8},{"quantity":50,"price":5}]'>
                <form class="buy-widget"></form>
            </div>
        `;

        const form = document.querySelector('.buy-widget');
        const addToCartInstance = { el: form, $emitter: new NativeEventEmitter(form) };
        addToCartInstances.push(addToCartInstance);

        new AddToCartEvent().execute();

        const formData = new FormData();
        formData.append('lineItems[product-123][id]', 'product-123');
        formData.append('lineItems[product-123][quantity]', quantity);

        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', expect.objectContaining({
            'value': value,
            'items': [expect.objectContaining({ 'price': price })],
        }));
    });

    test('selects the price tier from the quantity the cart line will hold', () => {
        // 9 in the cart plus 2 added are priced as 11 units by the cart
        document.body.innerHTML = `
            <meta property="product:price:currency" content="EUR">
            <div class="product-detail-buy"
                 data-product-prices='[{"quantity":10,"price":10},{"quantity":11,"price":8}]'>
                <form class="buy-widget"></form>
            </div>
            <div class="hidden-line-items-information">
                <span class="hidden-line-item" data-id="product-123" data-line-item-id="product-123" data-quantity="9"></span>
            </div>
        `;

        const form = document.querySelector('.buy-widget');
        const addToCartInstance = { el: form, $emitter: new NativeEventEmitter(form) };
        addToCartInstances.push(addToCartInstance);

        new AddToCartEvent().execute();

        const formData = new FormData();
        formData.append('lineItems[product-123][id]', 'product-123');
        formData.append('lineItems[product-123][quantity]', '2');

        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', expect.objectContaining({
            'value': 16,
            'items': [expect.objectContaining({ 'price': 8, 'quantity': 2 })],
        }));
    });

    test.each([
        ['a line an API client added under its own id', 'data-id="product-123" data-line-item-id="custom-line"', 10],
        ['a theme markup without the line item id', 'data-id="product-123"', 8],
    ])('only counts the cart line the add stacks onto, not %s', (label, attributes, price) => {
        document.body.innerHTML = `
            <div class="product-detail-buy"
                 data-product-prices='[{"quantity":10,"price":10},{"quantity":11,"price":8}]'>
                <form class="buy-widget"></form>
            </div>
            <div class="hidden-line-items-information">
                <span class="hidden-line-item" ${attributes} data-quantity="9"></span>
            </div>
        `;

        const form = document.querySelector('.buy-widget');
        const addToCartInstance = { el: form, $emitter: new NativeEventEmitter(form) };
        addToCartInstances.push(addToCartInstance);

        new AddToCartEvent().execute();

        const formData = new FormData();
        formData.append('lineItems[product-123][id]', 'product-123');
        formData.append('lineItems[product-123][quantity]', '2');

        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', expect.objectContaining({
            'items': [expect.objectContaining({ 'price': price })],
        }));
    });

    describe('hands brand and category to the cart', () => {
        const breadcrumb = `
            <nav aria-label="breadcrumb">
                <span class="breadcrumb-title">Damen</span>
                <span class="breadcrumb-title">Schuhe</span>
            </nav>
        `;

        function renderCard() {
            return `
                <div class="product-box" data-product-information='{"id":"product-123","name":"Boot","brand":"Acme","price":10,"sku":"SW1"}'>
                    <form class="buy-form"></form>
                </div>
            `;
        }

        function submit(formData) {
            const form = document.querySelector('.buy-form');
            const addToCartInstance = { el: form, $emitter: new NativeEventEmitter(form) };
            addToCartInstances.push(addToCartInstance);
            new AddToCartEvent().execute();
            addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

            return formData;
        }

        function formFor(productId = 'product-123') {
            const formData = new FormData();
            formData.append(`lineItems[${productId}][id]`, productId);
            formData.append(`lineItems[${productId}][quantity]`, '1');

            return formData;
        }

        afterEach(() => {
            delete window.activeRoute;
        });

        test('adds the card brand and the listing breadcrumb to the payload', () => {
            window.activeRoute = 'frontend.navigation.page';
            document.body.innerHTML = breadcrumb + renderCard();

            const formData = submit(formFor());

            expect(formData.get('lineItems[product-123][payload][manufacturerName]')).toBe('Acme');
            expect(formData.get('lineItems[product-123][payload][categoryNames][0]')).toBe('Damen');
            expect(formData.get('lineItems[product-123][payload][categoryNames][1]')).toBe('Schuhe');
        });

        test('sends the brand but no category on a page without breadcrumb', () => {
            window.activeRoute = 'frontend.home.page';
            document.body.innerHTML = renderCard();

            const formData = submit(formFor());

            expect(formData.get('lineItems[product-123][payload][manufacturerName]')).toBe('Acme');
            expect([...formData.keys()].some(key => key.includes('[categoryNames]'))).toBe(false);
        });

        test('sends no category for a cross selling card on a product detail page', () => {
            // the breadcrumb of a product detail page belongs to the product of the page
            window.activeRoute = 'frontend.detail.page';
            document.body.innerHTML = breadcrumb + renderCard();

            const formData = submit(formFor());

            expect(formData.get('lineItems[product-123][payload][manufacturerName]')).toBe('Acme');
            expect([...formData.keys()].some(key => key.includes('[categoryNames]'))).toBe(false);
        });

        test('keeps what the form already posts', () => {
            window.activeRoute = 'frontend.navigation.page';
            document.body.innerHTML = breadcrumb + renderCard();

            const formData = formFor();
            formData.append('lineItems[product-123][payload][manufacturerName]', 'Posted');
            formData.append('lineItems[product-123][payload][categoryNames][0]', 'Posted category');
            submit(formData);

            expect(formData.getAll('lineItems[product-123][payload][manufacturerName]')).toEqual(['Posted']);
            expect(formData.get('lineItems[product-123][payload][categoryNames][0]')).toBe('Posted category');
            expect(formData.has('lineItems[product-123][payload][categoryNames][1]')).toBe(false);
        });

        test('leaves the buy widget of the product detail page alone', () => {
            window.activeRoute = 'frontend.detail.page';
            document.body.innerHTML = breadcrumb + '<div class="product-detail-buy"><form class="buy-form"></form></div>';

            const formData = submit(formFor());

            expect([...formData.keys()].some(key => key.includes('[payload]'))).toBe(false);
        });

        test('sends nothing extra when the event is disabled, for example without consent', () => {
            window.activeRoute = 'frontend.navigation.page';
            document.body.innerHTML = breadcrumb + renderCard();

            const form = document.querySelector('.buy-form');
            const addToCartInstance = { el: form, $emitter: new NativeEventEmitter(form) };
            addToCartInstances.push(addToCartInstance);
            const event = new AddToCartEvent();
            event.execute();
            event.disable();

            const formData = formFor();
            addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

            expect([...formData.keys()].some(key => key.includes('[payload]'))).toBe(false);
        });
    });
});
