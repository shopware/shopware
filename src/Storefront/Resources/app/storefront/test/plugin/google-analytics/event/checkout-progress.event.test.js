import CheckoutProgressEvent from 'src/plugin/google-analytics/events/checkout-progress.event';

describe('plugin/google-analytics/events/checkout-progress.event', () => {
    let event;

    beforeEach(() => {
        window.gtag = jest.fn();
        window.gtagIsTagManager = false;
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR">
                <span class="hidden-line-item" data-id="product-1" data-sku="SW10000" data-name="Shirt" data-quantity="2" data-price="10"></span>
            </div>
        `;
        event = new CheckoutProgressEvent();
        event.active = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('only supports the cart page', () => {
        expect(event.supports(null, null, 'frontend.checkout.cart.page')).toBe(true);
        expect(event.supports(null, null, 'frontend.checkout.confirm.page')).toBe(false);
    });

    test('reports the line items of the cart', () => {
        event.execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'checkout_progress', expect.objectContaining({
            items: [expect.objectContaining({ item_id: 'SW10000', quantity: 2, price: 10 })],
        }));
    });

    test('does nothing without consent', () => {
        event.active = false;
        event.execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });
});
