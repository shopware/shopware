import BeginCheckoutEvent from 'src/plugin/google-analytics/events/begin-checkout.event';

describe('plugin/google-analytics/events/begin-checkout.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
        document.body.innerHTML = `
            <a class="begin-checkout-btn" href="/checkout/confirm">Checkout</a>
            <div class="hidden-line-items-information" data-currency="EUR" data-value="90">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-name="Test Product"
                    data-quantity="1"
                    data-price="100">
                </span>
                <span class="hidden-line-item-coupon" data-code="SAVE10"></span>
            </div>
        `;
    });

    // the base class subscribes the handlers of `getEvents()` to the off-canvas cart plugin
    function openOffCanvasCart(event) {
        event.getEvents().offCanvasOpened();
    }

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('reports begin_checkout from the off-canvas cart with the position price and the coupon', () => {
        const event = new BeginCheckoutEvent();
        openOffCanvasCart(event);

        document.querySelector('.begin-checkout-btn').click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'begin_checkout', {
            'currency': 'EUR',
            'value': 90,
            'coupon': 'SAVE10',
            'items': [{
                item_id: 'product-123',
                item_name: 'Test Product',
                quantity: 1,
                price: 100,
            }],
        });
    });

    test('reports once when the off-canvas cart opens several times', () => {
        const handlers = new BeginCheckoutEvent().getEvents();
        handlers.offCanvasOpened();
        handlers.offCanvasOpened();

        document.querySelector('.begin-checkout-btn').click();

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    test('reports nothing while disabled', () => {
        const event = new BeginCheckoutEvent();
        event.disable();

        event._onBeginCheckout();

        expect(window.gtag).not.toHaveBeenCalled();
    });
});
