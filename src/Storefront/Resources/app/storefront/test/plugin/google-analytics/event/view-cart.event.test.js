import ViewCartEvent from 'src/plugin/google-analytics/events/view-cart.event';

describe('plugin/google-analytics/events/view-cart.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
        window.activeRoute = 'frontend.checkout.cart.page';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
        delete window.trackOffcanvasCart;
        delete window.activeRoute;
    });

    test('supports returns true on cart page', () => {
        expect(new ViewCartEvent().supports('', '', 'frontend.checkout.cart.page')).toBe(true);
    });

    test('supports returns false on other pages when offcanvas tracking disabled', () => {
        window.trackOffcanvasCart = '0';
        expect(new ViewCartEvent().supports('', '', 'frontend.detail.page')).toBe(false);
    });

    test('supports returns true on other pages when offcanvas tracking enabled', () => {
        window.trackOffcanvasCart = '1';
        expect(new ViewCartEvent().supports('', '', 'frontend.detail.page')).toBe(true);
    });

    test('fires view_cart event with line items on cart page', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-name="Test Product"
                    data-quantity="2"
                    data-price="99.99">
                </span>
            </div>
        `;

        new ViewCartEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_cart', {
            'currency': 'EUR',
            'value': '199.98',
            'items': [
                {
                    id: 'product-123',
                    name: 'Test Product',
                    quantity: '2',
                    price: '99.99',
                    brand: null,
                },
            ],
        });
    });

    test('does not fire event when no line items exist', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="0"></div>
        `;

        new ViewCartEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('fires view_cart event when offcanvas opens and tracking enabled', () => {
        window.trackOffcanvasCart = '1';
        window.activeRoute = 'frontend.detail.page';
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="99.99">
                <span class="hidden-line-item"
                    data-id="product-456"
                    data-name="Another Product"
                    data-quantity="1"
                    data-price="99.99">
                </span>
            </div>
        `;

        const event = new ViewCartEvent();
        event.execute();

        // Simulate offcanvas opened (only happens via cart button click when tracking is enabled)
        event._onOffCanvasOpened();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_cart', {
            'currency': 'EUR',
            'value': '99.99',
            'items': [
                {
                    id: 'product-456',
                    name: 'Another Product',
                    quantity: '1',
                    price: '99.99',
                    brand: null,
                },
            ],
        });
    });
});

