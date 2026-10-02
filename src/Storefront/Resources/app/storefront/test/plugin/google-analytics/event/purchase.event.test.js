import CheckoutStepHelper from 'src/plugin/google-analytics/checkout-step.helper';
import PurchaseEvent from 'src/plugin/google-analytics/events/purchase.event';

describe('plugin/google-analytics/events/purchase.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
        window.trackOrders = true;
        window.sessionStorage.clear();
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

    test('clears the reported checkout steps so the next checkout reports them again', () => {
        CheckoutStepHelper.markReported('add_shipping_info');
        CheckoutStepHelper.markReported('add_payment_info');

        document.body.innerHTML = `
            <div class="finish-ordernumber" data-order-number="10001"></div>
            <div class="hidden-line-items-information" data-currency="EUR"></div>
        `;

        new PurchaseEvent().execute();

        expect(CheckoutStepHelper.hasReported('add_shipping_info')).toBe(false);
        expect(CheckoutStepHelper.hasReported('add_payment_info')).toBe(false);
    });

    test('fires purchase event with order number and line items', () => {
        document.body.innerHTML = `
            <div class="finish-ordernumber" data-order-number="10001"></div>
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98" data-tax="31.93" data-shipping="4.99">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-sku="product-123"
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
            'value': 199.98,
            'tax': 31.93,
            'shipping': 4.99,
            'items': [
                {
                    item_id: 'product-123',
                    item_name: 'Test Product',
                    quantity: 2,
                    price: 99.99,
                },
            ],
        });
    });

    test('fires purchase event with the applied coupon and the item variant', () => {
        document.body.innerHTML = `
            <div class="finish-ordernumber" data-order-number="10001"></div>
            <div class="hidden-line-items-information" data-currency="EUR" data-tax="15.96" data-shipping="0">
                <span class="hidden-line-item-coupon" data-code="SAVE20"></span>
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-sku="SW10000.1"
                    data-name="Test Product"
                    data-quantity="1"
                    data-price="99.99"
                    data-variant="Red, L">
                </span>
            </div>
        `;

        new PurchaseEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'purchase', {
            'transaction_id': '10001',
            'currency': 'EUR',
            'value': 99.99,
            'tax': 15.96,
            'shipping': 0,
            'coupon': 'SAVE20',
            'items': [
                {
                    item_id: 'SW10000.1',
                    item_name: 'Test Product',
                    quantity: 1,
                    price: 99.99,
                    item_variant: 'Red, L',
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

});
