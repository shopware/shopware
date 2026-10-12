import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';
import RemoveFromWishlistEvent from 'src/plugin/google-analytics/events/remove-from-wishlist.event';

describe('plugin/google-analytics/events/remove-from-wishlist.event', () => {
    let removeFromWishlistEvent;

    beforeEach(() => {
        window.gtag = jest.fn();

        // Mock PluginManager
        window.PluginManager = {
            getPlugin: jest.fn(() => null),
            initializePluginsInParentElement: jest.fn(),
        };

        removeFromWishlistEvent = new RemoveFromWishlistEvent();
        removeFromWishlistEvent.active = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true', async () => {
        expect(removeFromWishlistEvent.supports()).toBe(true);
    });

    test('fires remove_from_wishlist event with product page data', async () => {
        document.body.innerHTML = `
            <h1 class="product-detail-name">Test Product</h1>
            <div itemprop="brand"><meta itemprop="name" content="Test Brand"></div>
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="99.99">
        `;

        await removeFromWishlistEvent._sendEvent('product-123');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': 'EUR',
            'value': 99.99,
            'items': [{
                'item_id': 'product-123',
                'item_name': 'Test Product',
                'item_brand': 'Test Brand',
                'price': 99.99,
            }],
        });
    });

    test('fires remove_from_wishlist event with line item data on checkout pages', async () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                <span class="hidden-line-item"
                    data-id="product-456"
                    data-name="Line Item Product"
                    data-quantity="1"
                    data-price="49.99"
                    data-brand="Line Item Brand"
                    data-category-1="Category 1"
                    data-category-2="Category 2">
                </span>
            </div>
        `;

        await removeFromWishlistEvent._sendEvent('product-456');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': 'EUR',
            'value': 49.99,
            'items': [{
                'item_id': 'product-456',
                'item_name': 'Line Item Product',
                'item_brand': 'Line Item Brand',
                'price': 49.99,
                'item_category': 'Category 1',
                'item_category2': 'Category 2',
            }],
        });
    });

    test('does not fire event on form submit when not active', async () => {
        removeFromWishlistEvent.active = false;

        const form = document.createElement('form');
        form.classList.add('product-wishlist-form');
        form.setAttribute('action', '/wishlist/product/delete/product-123');

        await removeFromWishlistEvent._onFormSubmit({ target: form });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not fire event on product removed when not active', async () => {
        removeFromWishlistEvent.active = false;

        await removeFromWishlistEvent._onProductRemoved({
            detail: { productId: 'product-123' },
        });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not fire event when productId is missing from event', async () => {
        await removeFromWishlistEvent._onProductRemoved({
            detail: {},
        });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('extracts product ID from form action URL', async () => {
        const form = document.createElement('form');
        form.setAttribute('action', '/wishlist/product/delete/abc123-def456');

        const productId = removeFromWishlistEvent._extractProductIdFromForm(form);

        expect(productId).toBe('abc123-def456');
    });

    test('returns null when form action does not match pattern', async () => {
        const form = document.createElement('form');
        form.setAttribute('action', '/some/other/url');

        const productId = removeFromWishlistEvent._extractProductIdFromForm(form);

        expect(productId).toBeNull();
    });

    test('omits unavailable optional values', async () => {
        document.body.innerHTML = '';

        await removeFromWishlistEvent._sendEvent('product-unknown');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'items': [{
                'item_id': 'product-unknown',
            }],
        });
    });

    test('prefers product page data over line item data', async () => {
        document.body.innerHTML = `
            <h1 class="product-detail-name">Product Page Name</h1>
            <div itemprop="brand"><meta itemprop="name" content="Product Page Brand"></div>
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="79.99">
            <div class="hidden-line-items-information" data-currency="USD" data-value="100.00">
                <span class="hidden-line-item"
                    data-id="product-789"
                    data-name="Line Item Name"
                    data-price="50.00"
                    data-brand="Line Item Brand">
                </span>
            </div>
        `;

        await removeFromWishlistEvent._sendEvent('product-789');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': 'EUR',
            'value': 79.99,
            'items': [{
                'item_id': 'product-789',
                'item_name': 'Product Page Name',
                'item_brand': 'Product Page Brand',
                'price': 79.99,
            }],
        });
    });

    test('falls back to line item data when product page data has no name', async () => {
        document.body.innerHTML = `
            <meta property="product:price:currency" content="EUR">
            <div class="hidden-line-items-information" data-currency="USD" data-value="100.00">
                <span class="hidden-line-item"
                    data-id="product-fallback"
                    data-name="Fallback Name"
                    data-price="25.00"
                    data-brand="Fallback Brand">
                </span>
            </div>
        `;

        await removeFromWishlistEvent._sendEvent('product-fallback');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': 'USD',
            'value': 25.00,
            'items': [{
                'item_id': 'product-fallback',
                'item_name': 'Fallback Name',
                'item_brand': 'Fallback Brand',
                'price': 25,
            }],
        });
    });

    test('fires event on form submit for wishlist form', async () => {
        document.body.innerHTML = `
            <h1 class="product-detail-name">Test Product</h1>
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="99.99">
        `;

        const form = document.createElement('form');
        form.classList.add('product-wishlist-form');
        form.setAttribute('action', '/wishlist/product/delete/abc123-def456-789');

        form.submit = jest.fn();
        const submitEvent = { target: form, defaultPrevented: false, preventDefault: jest.fn() };

        await removeFromWishlistEvent._onFormSubmit(submitEvent);

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', expect.objectContaining({
            'items': [{
                'item_id': 'abc123-def456-789',
                'item_name': 'Test Product',
                'price': 99.99,
            }],
        }));
        // the removal is held back until the event is sent, then submitted
        expect(submitEvent.preventDefault).toHaveBeenCalled();
        expect(form.submit).toHaveBeenCalledTimes(1);
    });

    test('submits the removal after a second when the category lookup hangs', async () => {
        jest.useFakeTimers();
        document.body.innerHTML = '<h1 class="product-detail-name">Test Product</h1>';

        const resolveCategories = jest.spyOn(ProductPageHelper, 'resolveCategories')
            .mockReturnValue(new Promise(() => {}));

        const form = document.createElement('form');
        form.classList.add('product-wishlist-form');
        form.setAttribute('action', '/wishlist/product/delete/abc123-def456-789');
        form.submit = jest.fn();

        const submitted = removeFromWishlistEvent._onFormSubmit({ target: form, defaultPrevented: false, preventDefault: jest.fn() });
        expect(form.submit).not.toHaveBeenCalled();

        await jest.advanceTimersByTimeAsync(1000);
        await submitted;

        expect(form.submit).toHaveBeenCalledTimes(1);

        resolveCategories.mockRestore();
        jest.useRealTimers();
    });

    test('does not take over a submit another handler already handles', async () => {
        document.body.innerHTML = '<h1 class="product-detail-name">Test Product</h1>';

        const form = document.createElement('form');
        form.classList.add('product-wishlist-form');
        form.setAttribute('action', '/wishlist/product/delete/abc123-def456-789');
        form.submit = jest.fn();
        const submitEvent = { target: form, defaultPrevented: true, preventDefault: jest.fn() };

        await removeFromWishlistEvent._onFormSubmit(submitEvent);

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', expect.anything());
        expect(submitEvent.preventDefault).not.toHaveBeenCalled();
        expect(form.submit).not.toHaveBeenCalled();
    });

    test('does not fire event on form submit for non-wishlist form', async () => {
        const form = document.createElement('form');
        form.classList.add('some-other-form');

        await removeFromWishlistEvent._onFormSubmit({ target: form });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not report the product when the consent was revoked during the category request', async () => {
        document.body.innerHTML = '<h1 class="product-detail-name">Test Product</h1>';

        let answer;
        const resolveCategories = jest.spyOn(ProductPageHelper, 'resolveCategories')
            .mockReturnValue(new Promise(resolve => { answer = resolve; }));

        const sent = removeFromWishlistEvent._onProductRemoved({ detail: { productId: 'product-123' } });
        removeFromWishlistEvent.disable();
        answer({ item_category: 'Clothing' });
        await sent;

        expect(window.gtag).not.toHaveBeenCalled();

        resolveCategories.mockRestore();
    });
});
