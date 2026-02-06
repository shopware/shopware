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

    test('supports returns true', () => {
        expect(removeFromWishlistEvent.supports()).toBe(true);
    });

    test('fires remove_from_wishlist event with product page data', () => {
        document.body.innerHTML = `
            <h1 class="product-detail-name">Test Product</h1>
            <div itemprop="brand"><meta itemprop="name" content="Test Brand"></div>
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="99.99">
        `;

        removeFromWishlistEvent._sendEvent('product-123');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': 'EUR',
            'value': '99.99',
            'items': [{
                'id': 'product-123',
                'name': 'Test Product',
                'brand': 'Test Brand',
            }],
        });
    });

    test('fires remove_from_wishlist event with line item data on checkout pages', () => {
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

        removeFromWishlistEvent._sendEvent('product-456');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': 'EUR',
            'value': '49.99',
            'items': [{
                'id': 'product-456',
                'name': 'Line Item Product',
                'brand': 'Line Item Brand',
                'item_category': 'Category 1',
                'item_category2': 'Category 2',
            }],
        });
    });

    test('does not fire event on form submit when not active', () => {
        removeFromWishlistEvent.active = false;

        const form = document.createElement('form');
        form.classList.add('product-wishlist-form');
        form.setAttribute('action', '/wishlist/product/delete/product-123');

        removeFromWishlistEvent._onFormSubmit({ target: form });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not fire event on product removed when not active', () => {
        removeFromWishlistEvent.active = false;

        removeFromWishlistEvent._onProductRemoved({
            detail: { productId: 'product-123' },
        });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not fire event when productId is missing from event', () => {
        removeFromWishlistEvent._onProductRemoved({
            detail: {},
        });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('extracts product ID from form action URL', () => {
        const form = document.createElement('form');
        form.setAttribute('action', '/wishlist/product/delete/abc123-def456');

        const productId = removeFromWishlistEvent._extractProductIdFromForm(form);

        expect(productId).toBe('abc123-def456');
    });

    test('returns null when form action does not match pattern', () => {
        const form = document.createElement('form');
        form.setAttribute('action', '/some/other/url');

        const productId = removeFromWishlistEvent._extractProductIdFromForm(form);

        expect(productId).toBeNull();
    });

    test('fires event with undefined values when no product data available', () => {
        document.body.innerHTML = '';

        removeFromWishlistEvent._sendEvent('product-unknown');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': undefined,
            'value': undefined,
            'items': [{
                'id': 'product-unknown',
                'name': undefined,
                'brand': undefined,
            }],
        });
    });

    test('prefers product page data over line item data', () => {
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

        removeFromWishlistEvent._sendEvent('product-789');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': 'EUR',
            'value': '79.99',
            'items': [{
                'id': 'product-789',
                'name': 'Product Page Name',
                'brand': 'Product Page Brand',
            }],
        });
    });

    test('falls back to line item data when product page data has no name', () => {
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

        removeFromWishlistEvent._sendEvent('product-fallback');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': 'USD',
            'value': '25.00',
            'items': [{
                'id': 'product-fallback',
                'name': 'Fallback Name',
                'brand': 'Fallback Brand',
            }],
        });
    });

    test('fires event on form submit for wishlist form', () => {
        document.body.innerHTML = `
            <h1 class="product-detail-name">Test Product</h1>
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="99.99">
        `;

        const form = document.createElement('form');
        form.classList.add('product-wishlist-form');
        form.setAttribute('action', '/wishlist/product/delete/abc123-def456-789');

        removeFromWishlistEvent._onFormSubmit({ target: form });

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', expect.objectContaining({
            'items': [{
                'id': 'abc123-def456-789',
                'name': 'Test Product',
                'brand': undefined,
            }],
        }));
    });

    test('does not fire event on form submit for non-wishlist form', () => {
        const form = document.createElement('form');
        form.classList.add('some-other-form');

        removeFromWishlistEvent._onFormSubmit({ target: form });

        expect(window.gtag).not.toHaveBeenCalled();
    });
});
