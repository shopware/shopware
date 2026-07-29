import BeginCheckoutEvent from 'src/plugin/google-analytics/events/begin-checkout.event';
import CheckoutStepHelper from 'src/plugin/google-analytics/checkout-step.helper';

describe('plugin/google-analytics/events/begin-checkout.event', () => {
    let beginCheckoutEvent;

    beforeEach(() => {
        window.gtag = jest.fn();
        window.sessionStorage.clear();

        // Mock PluginManager for EventAwareAnalyticsEvent
        window.PluginManager = {
            getPlugin: jest.fn(() => null),
            initializePluginsInParentElement: jest.fn(),
        };

        beginCheckoutEvent = new BeginCheckoutEvent();
        beginCheckoutEvent.active = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    /**
     * `getEvents()` binds the click handler as a side effect and is called once per plugin
     * subscription, so it is kept out of `openOffCanvas()` to match the production flow.
     */
    function subscribe() {
        beginCheckoutEvent.getEvents();
    }

    function openOffCanvas() {
        beginCheckoutEvent._offCanvasOpened();
    }

    test('supports returns true', () => {
        expect(beginCheckoutEvent.supports('', '', 'frontend.detail.page')).toBe(true);
    });

    test('getPluginName returns OffCanvasCart', () => {
        expect(beginCheckoutEvent.getPluginName()).toBe('OffCanvasCart');
    });

    test('getEvents subscribes to offCanvasOpened', () => {
        expect(Object.keys(beginCheckoutEvent.getEvents())).toEqual(['offCanvasOpened']);
    });

    test('fires begin_checkout when the checkout button in the offcanvas is clicked', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-sku="product-123"
                    data-name="Test Product"
                    data-quantity="2"
                    data-price="99.99">
                </span>
            </div>
            <button class="begin-checkout-btn"></button>
        `;

        subscribe();
        openOffCanvas();
        document.querySelector('.begin-checkout-btn').click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'begin_checkout', {
            'currency': 'EUR',
            'value': 199.98,
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

    test('clears the reported checkout steps so the new checkout reports them again', () => {
        CheckoutStepHelper.markReported('add_shipping_info');
        CheckoutStepHelper.markReported('add_payment_info');

        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR"></div>
            <button class="begin-checkout-btn"></button>
        `;

        subscribe();
        openOffCanvas();
        document.querySelector('.begin-checkout-btn').click();

        expect(CheckoutStepHelper.hasReported('add_shipping_info')).toBe(false);
        expect(CheckoutStepHelper.hasReported('add_payment_info')).toBe(false);
    });

    test('fires once per click even when the offcanvas is opened repeatedly', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR"></div>
            <button class="begin-checkout-btn"></button>
        `;

        subscribe();

        // reopening the offcanvas must not stack another click listener on the same button
        openOffCanvas();
        openOffCanvas();
        openOffCanvas();

        document.querySelector('.begin-checkout-btn').click();

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    test('does nothing when the offcanvas has no checkout button', () => {
        document.body.innerHTML = '<div class="hidden-line-items-information" data-currency="EUR"></div>';

        subscribe();

        expect(() => openOffCanvas()).not.toThrow();
        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not fire and does not reset when the event is disabled', () => {
        CheckoutStepHelper.markReported('add_shipping_info');

        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR"></div>
            <button class="begin-checkout-btn"></button>
        `;

        beginCheckoutEvent.disable();

        subscribe();
        openOffCanvas();
        document.querySelector('.begin-checkout-btn').click();

        expect(window.gtag).not.toHaveBeenCalled();
        expect(CheckoutStepHelper.hasReported('add_shipping_info')).toBe(true);
    });
});
