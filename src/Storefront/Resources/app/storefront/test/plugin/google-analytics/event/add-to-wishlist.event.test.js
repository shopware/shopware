import AddToWishlistEvent from 'src/plugin/google-analytics/events/add-to-wishlist.event';

describe('plugin/google-analytics/events/add-to-wishlist.event', () => {
    let addToWishlistEvent;

    beforeEach(() => {
        window.gtag = jest.fn();

        // Mock PluginManager for EventAwareAnalyticsEvent
        window.PluginManager = {
            getPlugin: jest.fn(() => null),
            initializePluginsInParentElement: jest.fn(),
        };

        addToWishlistEvent = new AddToWishlistEvent();
        addToWishlistEvent.active = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true', () => {
        expect(addToWishlistEvent.supports()).toBe(true);
    });

    test('getPluginName returns WishlistStorage', () => {
        expect(addToWishlistEvent.getPluginName()).toBe('WishlistStorage');
    });

    test('fires add_to_wishlist event with product page data', () => {
        document.body.innerHTML = `
            <h1 class="product-detail-name">Test Product</h1>
            <div itemprop="brand"><meta itemprop="name" content="Test Brand"></div>
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="99.99">
        `;

        addToWishlistEvent._onProductAdded({
            detail: { productId: 'product-123' },
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_wishlist', {
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

    test('fires add_to_wishlist event with line item data on checkout pages', () => {
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

        addToWishlistEvent._onProductAdded({
            detail: { productId: 'product-456' },
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_wishlist', {
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

    test('does not fire event when not active', () => {
        addToWishlistEvent.active = false;

        addToWishlistEvent._onProductAdded({
            detail: { productId: 'product-123' },
        });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not fire event when productId is missing', () => {
        addToWishlistEvent._onProductAdded({
            detail: {},
        });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('omits unavailable optional values', () => {
        document.body.innerHTML = '';

        addToWishlistEvent._onProductAdded({
            detail: { productId: 'product-unknown' },
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_wishlist', {
            'items': [{
                'item_id': 'product-unknown',
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

        addToWishlistEvent._onProductAdded({
            detail: { productId: 'product-789' },
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_wishlist', {
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

        addToWishlistEvent._onProductAdded({
            detail: { productId: 'product-fallback' },
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_wishlist', {
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
});
