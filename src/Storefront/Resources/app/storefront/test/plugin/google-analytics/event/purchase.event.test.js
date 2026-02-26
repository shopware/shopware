import PurchaseEvent from 'src/plugin/google-analytics/events/purchase.event';

describe('plugin/google-analytics/events/purchase.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
        window.trackOrders = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
        delete window.trackOrders;
    });

    test('supports returns true on finish page when trackOrders is enabled', () => {
        window.trackOrders = true;
        expect(new PurchaseEvent().supports('', '', 'frontend.checkout.finish.page')).toBe(true);
    });

    test('supports returns false when trackOrders is disabled', () => {
        window.trackOrders = false;
        expect(new PurchaseEvent().supports('', '', 'frontend.checkout.finish.page')).toBe(false);
    });

    test('supports returns false on other pages', () => {
        expect(new PurchaseEvent().supports('', '', 'frontend.checkout.confirm.page')).toBe(false);
    });

    test('fires purchase event with order number and line items', () => {
        document.body.innerHTML = `
            <div class="finish-ordernumber" data-order-number="10001"></div>
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98" data-tax="31.93" data-shipping="4.99">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-name="Test Product"
                    data-quantity="2"
                    data-price="99.99">
                </span>
            </div>
        `;

        new PurchaseEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'purchase', {
            'transaction_id': '10001',
            'currency': 'EUR',
            'value': '199.98',
            'tax': '31.93',
            'shipping': '4.99',
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

    test('does not fire event when order number element is missing', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98"></div>
        `;

        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
        new PurchaseEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
        expect(consoleSpy).toHaveBeenCalled();
        consoleSpy.mockRestore();
    });

    test('does not fire event when order number is empty', () => {
        document.body.innerHTML = `
            <div class="finish-ordernumber" data-order-number=""></div>
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98"></div>
        `;

        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
        new PurchaseEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
        expect(consoleSpy).toHaveBeenCalled();
        consoleSpy.mockRestore();
    });

    test('includes user_data with sha256_email_address when enhanced conversion attribute is present', () => {
        const hashedEmail = 'a8af8341993604f29cd4e0e5a5a4b5d48c575436c38b28abbfd7d481f345d5db';

        document.body.innerHTML = `
            <div class="finish-ordernumber" data-order-number="10001" data-enhanced-conversion="${hashedEmail}"></div>
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98" data-tax="31.93" data-shipping="4.99">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-name="Test Product"
                    data-quantity="2"
                    data-price="99.99">
                </span>
            </div>
        `;

        new PurchaseEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'purchase', {
            'transaction_id': '10001',
            'currency': 'EUR',
            'value': '199.98',
            'tax': '31.93',
            'shipping': '4.99',
            'items': [
                {
                    id: 'product-123',
                    name: 'Test Product',
                    quantity: '2',
                    price: '99.99',
                    brand: null,
                },
            ],
            'user_data': {
                'sha256_email_address': hashedEmail,
            },
        });
    });

    test('does not include user_data when enhanced conversion attribute is absent', () => {
        document.body.innerHTML = `
            <div class="finish-ordernumber" data-order-number="10001"></div>
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98" data-tax="31.93" data-shipping="4.99">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-name="Test Product"
                    data-quantity="2"
                    data-price="99.99">
                </span>
            </div>
        `;

        new PurchaseEvent().execute();

        const callArgs = window.gtag.mock.calls[0][2];
        expect(callArgs).not.toHaveProperty('user_data');
    });
});

