import ViewCartEvent from 'src/plugin/google-analytics/events/view-cart.event';

describe('plugin/google-analytics/events/view-cart.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true on cart page', () => {
        expect(new ViewCartEvent().supports('', '', 'frontend.checkout.cart.page')).toBe(true);
    });

    test('supports returns false on other pages', () => {
        expect(new ViewCartEvent().supports('', '', 'frontend.detail.page')).toBe(false);
        expect(new ViewCartEvent().supports('', '', 'frontend.checkout.confirm.page')).toBe(false);
    });

    test('fires view_cart event with line items', () => {
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
});

