import BeginCheckoutOnCartEvent from 'src/plugin/google-analytics/events/begin-checkout-on-cart.event';

describe('plugin/google-analytics/events/begin-checkout-on-cart.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true on cart page', () => {
        expect(new BeginCheckoutOnCartEvent().supports('', '', 'frontend.checkout.cart.page')).toBe(true);
    });

    test('supports returns false on other pages', () => {
        expect(new BeginCheckoutOnCartEvent().supports('', '', 'frontend.detail.page')).toBe(false);
        expect(new BeginCheckoutOnCartEvent().supports('', '', 'frontend.checkout.confirm.page')).toBe(false);
    });

    test('fires begin_checkout event with currency and value when checkout button is clicked', () => {
        document.body.innerHTML = `
            <button class="begin-checkout-btn">Checkout</button>
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-name="Test Product"
                    data-quantity="2"
                    data-price="99.99">
                </span>
            </div>
        `;

        const event = new BeginCheckoutOnCartEvent();
        event.execute();

        const button = document.querySelector('.begin-checkout-btn');
        button.click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'begin_checkout', {
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

    test('does not register listener when checkout button is missing', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98"></div>
        `;

        const event = new BeginCheckoutOnCartEvent();
        event.execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });
});

