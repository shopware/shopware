import ViewItemEvent from 'src/plugin/google-analytics/events/view-item.event';

describe('plugin/google-analytics/events/view-item.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true on detail page', () => {
        expect(new ViewItemEvent().supports('', '', 'frontend.detail.page')).toBe(true);
    });

    test('supports returns false on other pages', () => {
        expect(new ViewItemEvent().supports('', '', 'frontend.checkout.cart.page')).toBe(false);
        expect(new ViewItemEvent().supports('', '', 'frontend.home.page')).toBe(false);
    });

    test('fires view_item event with product data', () => {
        document.body.innerHTML = `
            <div itemtype="https://schema.org/Product">
                <meta itemprop="productID" content="product-123">
                <span itemprop="name">Test Product</span>
                <div itemprop="brand">
                    <meta itemprop="name" content="Test Brand">
                </div>
            </div>
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="99.99">
            <nav aria-label="breadcrumb">
                <span class="breadcrumb-title">Category 1</span>
                <span class="breadcrumb-title">Category 2</span>
            </nav>
        `;

        new ViewItemEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item', {
            'items': [{
                'id': 'product-123',
                'name': 'Test Product',
                'brand': 'Test Brand',
                'item_category': 'Category 1',
                'item_category2': 'Category 2',
            }],
            'currency': 'EUR',
            'value': '99.99',
        });
    });

    test('does not fire event when product itemtype is missing', () => {
        document.body.innerHTML = `<div>No product here</div>`;

        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
        new ViewItemEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
        expect(consoleSpy).toHaveBeenCalled();
        consoleSpy.mockRestore();
    });

    test('does not fire event when product ID is missing', () => {
        document.body.innerHTML = `
            <div itemtype="https://schema.org/Product">
                <span itemprop="name">Test Product</span>
            </div>
        `;

        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
        new ViewItemEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
        expect(consoleSpy).toHaveBeenCalled();
        consoleSpy.mockRestore();
    });
});

