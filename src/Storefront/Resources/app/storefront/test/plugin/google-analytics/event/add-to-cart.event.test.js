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
            // the value of the added quantity, not the unit price
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

    describe('graduated prices and variants', () => {
        function submitFromBuyWidget(quantity, cartMarkup = '') {
            document.body.innerHTML = `
                <meta property="product:price:amount" content="10">
                <h1 class="product-detail-name">Shirt</h1>
                <div class="product-detail-buy" data-product-variant="Red, L"
                     data-product-prices='[{"quantity":10,"price":20},{"quantity":11,"price":15}]'>
                    <div class="form-slot"></div>
                </div>
                ${cartMarkup}
            `;

            const addToCartInstance = createMockPluginInstance();
            document.querySelector('.form-slot').appendChild(addToCartInstance.el);
            addToCartInstances.push(addToCartInstance);

            new AddToCartEvent().execute();

            const formData = new FormData();
            formData.append('lineItems[product-1][id]', 'product-1');
            formData.append('lineItems[product-1][quantity]', String(quantity));
            addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

            return window.gtag.mock.calls.find(call => call[1] === 'add_to_cart')[2];
        }

        test('reports the tier price of the added quantity and the variant', () => {
            const parameters = submitFromBuyWidget(2);

            expect(parameters.value).toBe(40);
            expect(parameters.items[0]).toEqual(expect.objectContaining({
                'price': 20,
                'quantity': 2,
                'item_variant': 'Red, L',
            }));
        });

        test('reports the tier of the quantity the cart holds after the add', () => {
            const parameters = submitFromBuyWidget(2, `
                <div class="hidden-line-items-information">
                    <span class="hidden-line-item" data-id="product-1" data-line-item-id="product-1" data-quantity="9"></span>
                </div>
            `);

            expect(parameters.value).toBe(30);
            expect(parameters.items[0].price).toBe(15);
        });

        // a theme can render the hidden line items without the line item id
        test('matches the line by the product id without a line item id', () => {
            const parameters = submitFromBuyWidget(2, `
                <div class="hidden-line-items-information">
                    <span class="hidden-line-item" data-id="product-1" data-quantity="9"></span>
                </div>
            `);

            expect(parameters.items[0].price).toBe(15);
        });

        test('ignores a line of the same product under another line item id', () => {
            const parameters = submitFromBuyWidget(2, `
                <div class="hidden-line-items-information">
                    <span class="hidden-line-item" data-id="product-1" data-line-item-id="other-line" data-quantity="9"></span>
                </div>
            `);

            expect(parameters.items[0].price).toBe(20);
        });
    });

    describe('cart payload of a product element', () => {
        const breadcrumb = `
            <nav aria-label="breadcrumb">
                <span class="breadcrumb-title">Clothing</span>
                <span class="breadcrumb-title">Shirts</span>
            </nav>
        `;

        function submitFromProductElement(markup, formData) {
            document.body.innerHTML = markup;

            const addToCartInstance = createMockPluginInstance();
            document.querySelector('.form-slot').appendChild(addToCartInstance.el);
            addToCartInstances.push(addToCartInstance);

            new AddToCartEvent().execute();
            addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

            return formData;
        }

        function createFormData() {
            const formData = new FormData();
            formData.append('lineItems[product-1][id]', 'product-1');
            formData.append('lineItems[product-1][quantity]', '1');

            return formData;
        }

        afterEach(() => {
            delete window.activeRoute;
        });

        test('posts the brand of the product element and the categories of the listing', () => {
            window.activeRoute = 'frontend.navigation.page';

            const formData = submitFromProductElement(`
                ${breadcrumb}
                <article data-product-information='{"id":"product-1","brand":"Brand"}'>
                    <div class="form-slot"></div>
                </article>
            `, createFormData());

            expect(formData.get('lineItems[product-1][payload][manufacturerName]')).toBe('Brand');
            expect(formData.get('lineItems[product-1][payload][categoryNames][0]')).toBe('Clothing');
            expect(formData.get('lineItems[product-1][payload][categoryNames][1]')).toBe('Shirts');
        });

        test('keeps a payload the form already posts', () => {
            window.activeRoute = 'frontend.navigation.page';
            const formData = createFormData();
            formData.append('lineItems[product-1][payload][manufacturerName]', 'Form Brand');
            formData.append('lineItems[product-1][payload][categoryNames][abc]', 'Form Category');

            submitFromProductElement(`
                ${breadcrumb}
                <article data-product-information='{"id":"product-1","brand":"Brand"}'>
                    <div class="form-slot"></div>
                </article>
            `, formData);

            expect(formData.get('lineItems[product-1][payload][manufacturerName]')).toBe('Form Brand');
            expect(formData.get('lineItems[product-1][payload][categoryNames][abc]')).toBe('Form Category');
            expect(formData.has('lineItems[product-1][payload][categoryNames][0]')).toBe(false);
        });

        test('posts no categories for a product element on a product detail page', () => {
            window.activeRoute = 'frontend.detail.page';

            const formData = submitFromProductElement(`
                ${breadcrumb}
                <article data-product-information='{"id":"product-1","brand":"Brand"}'>
                    <div class="form-slot"></div>
                </article>
            `, createFormData());

            expect(formData.get('lineItems[product-1][payload][manufacturerName]')).toBe('Brand');
            expect(formData.has('lineItems[product-1][payload][categoryNames][0]')).toBe(false);
        });

        test('posts nothing for a form outside of a product element', () => {
            window.activeRoute = 'frontend.navigation.page';

            const formData = submitFromProductElement(`
                ${breadcrumb}
                <div class="form-slot"></div>
            `, createFormData());

            expect([...formData.keys()].some(key => key.includes('[payload]'))).toBe(false);
        });
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

    test('reports no value when the price of the product is unknown', () => {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        const event = new AddToCartEvent();
        event.execute();

        const formData = new FormData();
        formData.append('lineItems[product-123][id]', 'product-123');
        formData.append('product-name', 'Test Product');
        formData.append('lineItems[product-123][quantity]', '2');

        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        const [, , parameters] = window.gtag.mock.calls[0];
        expect(parameters).not.toHaveProperty('value');
        expect(parameters.items[0]).not.toHaveProperty('price');
        expect(parameters.items[0]).toEqual(expect.objectContaining({ item_id: 'product-123', quantity: 2 }));
    });

    test('reports the unit price as value without a quantity in the form', () => {
        document.body.innerHTML = `
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="99.99">
        `;

        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        const event = new AddToCartEvent();
        event.execute();

        const formData = new FormData();
        formData.append('lineItems[product-123][id]', 'product-123');

        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', expect.objectContaining({
            'value': 99.99,
        }));
    });
});
