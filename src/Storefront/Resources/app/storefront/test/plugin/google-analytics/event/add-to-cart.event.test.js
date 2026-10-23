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

/**
 * The event resolves the categories of a product the page does not describe asynchronously.
 */
function flushPromises() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

describe('plugin/google-analytics/events/add-to-cart.event', () => {
    let addToCartInstances = [];
    let listingInstances = [];
    let offCanvasCartInstances = [];

    beforeEach(() => {
        addToCartInstances = [];
        listingInstances = [];
        offCanvasCartInstances = [];

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
            if (name === 'OffCanvasCart') {
                return offCanvasCartInstances;
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

    test('fires add_to_cart event when AddToCart plugin emits beforeFormSubmit', async () => {
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
        await flushPromises();

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
        async function submitFromBuyWidget(quantity, cartMarkup = '') {
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
            await flushPromises();

            return window.gtag.mock.calls.find(call => call[1] === 'add_to_cart')[2];
        }

        test('reports the tier price of the added quantity and the variant', async () => {
            const parameters = await submitFromBuyWidget(2);

            expect(parameters.value).toBe(40);
            expect(parameters.items[0]).toEqual(expect.objectContaining({
                'price': 20,
                'quantity': 2,
                'item_variant': 'Red, L',
            }));
        });

        test('reports the tier of the quantity the cart holds after the add', async () => {
            const parameters = await submitFromBuyWidget(2, `
                <div class="hidden-line-items-information">
                    <span class="hidden-line-item" data-id="product-1" data-line-item-id="product-1" data-quantity="9"></span>
                </div>
            `);

            expect(parameters.value).toBe(30);
            expect(parameters.items[0].price).toBe(15);
        });

        // a theme can render the hidden line items without the line item id
        test('matches the line by the product id without a line item id', async () => {
            const parameters = await submitFromBuyWidget(2, `
                <div class="hidden-line-items-information">
                    <span class="hidden-line-item" data-id="product-1" data-quantity="9"></span>
                </div>
            `);

            expect(parameters.items[0].price).toBe(15);
        });

        test('ignores a line of the same product under another line item id', async () => {
            const parameters = await submitFromBuyWidget(2, `
                <div class="hidden-line-items-information">
                    <span class="hidden-line-item" data-id="product-1" data-line-item-id="other-line" data-quantity="9"></span>
                </div>
            `);

            expect(parameters.items[0].price).toBe(20);
        });
    });

    test('reports the requested categories of a product element on a page without breadcrumb', async () => {
        window.activeRoute = 'frontend.home.page';
        window.router = { 'frontend.analytics.product-categories': '/widgets/analytics/product-categories' };
        global.fetch = jest.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve(['Clothing', 'Shirts']) }));
        document.body.innerHTML = `
            <div class="card product-box" data-product-information='{"id":"product-1","name":"Shirt","sku":"SW1","price":10}'>
                <div class="form-slot"></div>
            </div>
        `;

        const addToCartInstance = createMockPluginInstance();
        document.querySelector('.form-slot').appendChild(addToCartInstance.el);
        addToCartInstances.push(addToCartInstance);
        new AddToCartEvent().execute();

        const formData = new FormData();
        formData.append('lineItems[product-1][id]', 'product-1');
        formData.append('lineItems[product-1][quantity]', '1');
        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        // the cart payload is posted with the form and cannot wait for the request
        expect(formData.has('lineItems[product-1][payload][categoryNames][0]')).toBe(false);

        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/widgets/analytics/product-categories?productId=product-1', expect.anything());
        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', expect.objectContaining({
            'items': [expect.objectContaining({ 'item_id': 'SW1', 'item_category': 'Clothing', 'item_category2': 'Shirts' })],
        }));

        delete window.activeRoute;
        delete window.router;
        delete global.fetch;
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
                <div class="cms-element-product-listing-wrapper">
                    <article data-product-information='{"id":"product-1","brand":"Brand"}'>
                        <div class="form-slot"></div>
                    </article>
                </div>
            `, createFormData());

            expect(formData.get('lineItems[product-1][payload][manufacturerName]')).toBe('Brand');
            expect(formData.get('lineItems[product-1][payload][categoryNames][0]')).toBe('Clothing');
            expect(formData.get('lineItems[product-1][payload][categoryNames][1]')).toBe('Shirts');
        });

        // the categories of a slider product are requested with the add and kept for the cart
        test('posts no categories for a product element outside the listing of a category page', () => {
            window.activeRoute = 'frontend.navigation.page';

            const formData = submitFromProductElement(`
                ${breadcrumb}
                <div class="cms-element-product-slider">
                    <article data-product-information='{"id":"product-1","brand":"Brand"}'>
                        <div class="form-slot"></div>
                    </article>
                </div>
            `, createFormData());

            expect(formData.get('lineItems[product-1][payload][manufacturerName]')).toBe('Brand');
            expect([...formData.keys()].some(key => key.includes('[categoryNames]'))).toBe(false);
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

    test('stays silent for a reorder form, which posts no line items', () => {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        const event = new AddToCartEvent();
        event.execute();

        const formData = new FormData();
        formData.append('redirectTo', 'frontend.cart.offcanvas');

        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        expect(window.gtag).not.toHaveBeenCalled();
        expect(warn).not.toHaveBeenCalled();

        warn.mockRestore();
    });

    test('warns when a line item form carries no product id', () => {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        const event = new AddToCartEvent();
        event.execute();

        const formData = new FormData();
        formData.append('lineItems[product-123][quantity]', '2');

        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        expect(window.gtag).not.toHaveBeenCalled();
        expect(warn).toHaveBeenCalled();

        warn.mockRestore();
    });

    test('does not fire event when AddToCart plugin is not present', () => {
        // addToCartInstances is empty
        const event = new AddToCartEvent();
        event.execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not duplicate subscriptions when execute is called multiple times', async () => {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        const event = new AddToCartEvent();
        event.execute();
        event.execute(); // Call again

        // Trigger event
        const formData = new FormData();
        formData.append('lineItems[product-123][id]', 'product-123');
        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);
        await flushPromises();

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
        await flushPromises();
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
        await flushPromises();

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
        await flushPromises();

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    test('skips Listing subscription when Listing plugin is not present', async () => {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        // No Listing instances

        const event = new AddToCartEvent();
        event.execute();

        // Should not throw and should still subscribe to AddToCart
        const formData = new FormData();
        formData.append('lineItems[product-1][id]', 'product-1');
        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);
        await flushPromises();

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    test('reports no value when the price of the product is unknown', async () => {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstances.push(addToCartInstance);

        const event = new AddToCartEvent();
        event.execute();

        const formData = new FormData();
        formData.append('lineItems[product-123][id]', 'product-123');
        formData.append('product-name', 'Test Product');
        formData.append('lineItems[product-123][quantity]', '2');

        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);
        await flushPromises();

        const [, , parameters] = window.gtag.mock.calls[0];
        expect(parameters).not.toHaveProperty('value');
        expect(parameters.items[0]).not.toHaveProperty('price');
        expect(parameters.items[0]).toEqual(expect.objectContaining({ item_id: 'product-123', quantity: 2 }));
    });

    test('reports the unit price as value without a quantity in the form', async () => {
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
        await flushPromises();

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', expect.objectContaining({
            'value': 99.99,
        }));
    });

    test('does not fire when the consent is revoked while the categories are requested', async () => {
        window.activeRoute = 'frontend.home.page';
        window.localStorage.clear();
        window.router = { 'frontend.analytics.product-categories': '/widgets/analytics/product-categories' };
        document.body.innerHTML = `
            <div class="card product-box" data-product-information='{"id":"product-1","name":"Shirt","sku":"SW1","price":10}'>
                <div class="form-slot"></div>
            </div>
        `;

        const addToCartInstance = createMockPluginInstance();
        document.querySelector('.form-slot').appendChild(addToCartInstance.el);
        addToCartInstances.push(addToCartInstance);
        const event = new AddToCartEvent();
        event.execute();

        global.fetch = jest.fn(() => {
            event.disable();

            return Promise.resolve({ ok: true, json: () => Promise.resolve(['Clothing']) });
        });

        const formData = new FormData();
        formData.append('lineItems[product-1][id]', 'product-1');
        formData.append('lineItems[product-1][quantity]', '1');
        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        await flushPromises();

        expect(global.fetch).toHaveBeenCalled();
        expect(window.gtag).not.toHaveBeenCalled();
        // nothing of the response is kept for the cart either
        expect(window.localStorage.getItem('swGaProductCategories')).toBeNull();

        delete window.activeRoute;
        delete window.router;
        delete global.fetch;
    });

    // the order of the account: a shirt for 19.99 twice and a cap for 5 three times
    function submitReorder() {
        const addToCartInstance = createMockPluginInstance();
        addToCartInstance.el = document.createElement('form');
        addToCartInstance.el.innerHTML = `
            <span data-analytics-line-item data-id="product-1" data-sku="SW-1" data-quantity="2"
                  data-brand="Brand" data-variant="Red, XL" data-category-1="Clothing" data-category-2="Shirts"></span>
            <span data-analytics-line-item data-id="product-2" data-sku="SW-2" data-quantity="3" data-brand=""></span>
        `;
        addToCartInstance.$emitter = new NativeEventEmitter(addToCartInstance.el);
        addToCartInstances.push(addToCartInstance);

        const offCanvasCart = createMockPluginInstance();
        offCanvasCartInstances.push(offCanvasCart);

        const event = new AddToCartEvent();
        event.execute();

        // the reorder form posts only the redirect, the route resolves the products of the order
        const formData = new FormData();
        formData.append('redirectTo', 'frontend.cart.offcanvas');
        addToCartInstance.$emitter.publish('beforeFormSubmit', formData);

        return { event, form: addToCartInstance, offCanvasCart, formData };
    }

    // the cart the server returns after the reorder, priced with the current prices and the cart currency
    function returnedCart({ success = true, lineItems = null } = {}) {
        return `
            ${success ? '<div class="alert alert-success">2 products added</div>' : '<div class="alert alert-danger">Error</div>'}
            <div class="d-none hidden-line-items-information" data-currency="USD">
                ${lineItems ?? `
                    <span class="hidden-line-item" data-id="product-1" data-sku="SW-1" data-name="Shirt" data-quantity="3" data-price="25"></span>
                    <span class="hidden-line-item" data-id="product-2" data-sku="SW-2" data-name="Cap" data-quantity="3" data-price="6.5" data-brand="Caps Inc"></span>
                    <span class="hidden-line-item" data-id="product-3" data-sku="SW-3" data-name="Socks" data-quantity="1" data-price="4"></span>
                `}
            </div>
        `;
    }

    test('reports the reorder from the off-canvas cart the server returns', () => {
        const { offCanvasCart, formData } = submitReorder();

        expect(window.gtag).not.toHaveBeenCalled();

        offCanvasCart.$emitter.publish('offCanvasOpened', { response: returnedCart() });

        // price and currency are the current ones of the cart, the quantity is the one the reorder added
        expect(window.gtag).toHaveBeenCalledTimes(1);
        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', {
            'currency': 'USD',
            'value': 69.5,
            'items': [
                { item_id: 'SW-1', item_name: 'Shirt', quantity: 2, price: 25, item_brand: 'Brand', item_variant: 'Red, XL', item_category: 'Clothing', item_category2: 'Shirts' },
                { item_id: 'SW-2', item_name: 'Cap', quantity: 3, price: 6.5, item_brand: 'Caps Inc' },
            ],
        });
        expect([...formData.keys()].some(key => key.includes('[payload]'))).toBe(false);
    });

    test('reports only the products of the order the returned cart holds', () => {
        const { offCanvasCart } = submitReorder();

        offCanvasCart.$emitter.publish('offCanvasOpened', {
            response: returnedCart({
                lineItems: '<span class="hidden-line-item" data-id="product-2" data-sku="SW-2" data-name="Cap" data-price="6.5"></span>',
            }),
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', expect.objectContaining({
            'value': 19.5,
            'items': [expect.objectContaining({ item_id: 'SW-2', quantity: 3 })],
        }));
    });

    // the server shows the success alert only if it added every product, the cart can hold them from before
    test('reports nothing when the off-canvas cart shows no success', () => {
        const { offCanvasCart } = submitReorder();

        offCanvasCart.$emitter.publish('offCanvasOpened', { response: returnedCart({ success: false }) });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('reports the reorder once, a later opened off-canvas cart reports nothing', () => {
        const { offCanvasCart } = submitReorder();

        offCanvasCart.$emitter.publish('offCanvasOpened', { response: returnedCart() });
        offCanvasCart.$emitter.publish('offCanvasOpened', { response: returnedCart() });

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    test('requests the cart once for a shop that does not open the off-canvas cart', async () => {
        window.router = { 'frontend.cart.offcanvas': '/checkout/offcanvas' };
        global.fetch = jest.fn().mockResolvedValue({ ok: true, text: () => Promise.resolve(returnedCart({ success: false })) });
        const { form, offCanvasCart } = submitReorder();

        form.$emitter.publish('addToCartWithoutOffcanvas');
        await new Promise(resolve => setTimeout(resolve, 0));
        offCanvasCart.$emitter.publish('offCanvasOpened', { response: returnedCart() });

        // the flash message went with the response to the add, the requested cart has none
        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch).toHaveBeenCalledWith('/checkout/offcanvas', expect.any(Object));
        expect(window.gtag).toHaveBeenCalledTimes(1);
        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', expect.objectContaining({ 'currency': 'USD', 'value': 69.5 }));

        delete window.router;
        delete global.fetch;
    });

    test('reports nothing when tracking is disabled before the cart returns', () => {
        const { event, offCanvasCart } = submitReorder();

        event.disable();
        offCanvasCart.$emitter.publish('offCanvasOpened', { response: returnedCart() });

        expect(window.gtag).not.toHaveBeenCalled();
    });
});
