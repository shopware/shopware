import AddPaymentInfoEvent from 'src/plugin/google-analytics/events/add-payment-info.event';

describe('plugin/google-analytics/events/add-payment-info.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
        window.activeRoute = 'frontend.checkout.confirm.page';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true on checkout confirm page', () => {
        expect(new AddPaymentInfoEvent().supports('', '', 'frontend.checkout.confirm.page')).toBe(true);
    });

    test('supports returns false on other pages', () => {
        expect(new AddPaymentInfoEvent().supports('', '', 'frontend.checkout.cart.page')).toBe(false);
        expect(new AddPaymentInfoEvent().supports('', '', 'frontend.detail.page')).toBe(false);
    });

    test('fires add_payment_info event with payment type and line items', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-name="Test Product"
                    data-quantity="2"
                    data-price="99.99"
                    data-brand="Test Brand"
                    data-category-1="Category 1">
                </span>
            </div>
            <div class="payment-method-radio">
                <input type="radio" class="payment-method-input" checked>
                <div class="payment-method-description">
                    <strong>Credit Card</strong>
                </div>
            </div>
        `;

        new AddPaymentInfoEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_payment_info', {
            'currency': 'EUR',
            'value': '199.98',
            'payment_type': 'Credit Card',
            'items': [
                {
                    id: 'product-123',
                    name: 'Test Product',
                    quantity: '2',
                    price: '99.99',
                    brand: 'Test Brand',
                    item_category: 'Category 1',
                },
            ],
        });
    });

    test('does not fire event when no line items exist', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="0"></div>
            <div class="payment-method-radio">
                <input type="radio" class="payment-method-input" checked>
                <div class="payment-method-description">
                    <strong>Invoice</strong>
                </div>
            </div>
        `;

        new AddPaymentInfoEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('fires event with empty payment type when no payment method is selected', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="49.99">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-name="Test Product"
                    data-quantity="1"
                    data-price="49.99">
                </span>
            </div>
        `;

        new AddPaymentInfoEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_payment_info', {
            'currency': 'EUR',
            'value': '49.99',
            'payment_type': '',
            'items': [
                {
                    id: 'product-123',
                    name: 'Test Product',
                    quantity: '1',
                    price: '49.99',
                    brand: null,
                },
            ],
        });
    });
});

