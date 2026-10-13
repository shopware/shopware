import AddShippingInfoEvent from 'src/plugin/google-analytics/events/add-shipping-info.event';
import AddShippingInfoOnRegisterEvent from 'src/plugin/google-analytics/events/add-shipping-info-on-register.event';
import CheckoutStepHelper from 'src/plugin/google-analytics/checkout-step.helper';

describe('plugin/google-analytics/events/add-shipping-info-on-register.event', () => {
    let event;

    const cart = `
        <div class="hidden-line-items-information" data-currency="EUR">
            <span class="hidden-line-item" data-id="product-1" data-sku="SW1" data-name="Shirt" data-quantity="2" data-price="10"></span>
        </div>
    `;

    beforeEach(() => {
        window.gtag = jest.fn();
        window.gtagIsTagManager = false;
        window.sessionStorage.clear();
        CheckoutStepHelper.reset();
        event = new AddShippingInfoOnRegisterEvent();
        event.active = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    function renderRegisterPage(shippingTier = 'Standard') {
        document.body.innerHTML = `${cart}
            ${shippingTier === null ? '' : `<div class="d-none" data-analytics-shipping-tier="${shippingTier}"></div>`}
            <form class="register-form"></form>
        `;
    }

    function submitRegistration(validity = true, form = document.querySelector('.register-form')) {
        event._onFormSubmit({ target: form, detail: { validity } });
    }

    function renderConfirmPage(shippingTier) {
        document.body.innerHTML = `${cart}
            <div class="shipping-method-radio">
                <input class="shipping-method-input" type="radio" checked>
                <div class="shipping-method-description"><strong>${shippingTier}</strong></div>
            </div>
        `;
    }

    function shippingEvents() {
        return window.gtag.mock.calls.filter(call => call[1] === 'add_shipping_info').map(call => call[2]);
    }

    test('only supports the checkout register page', () => {
        expect(event.supports(null, null, 'frontend.checkout.register.page')).toBe(true);
        expect(event.supports(null, null, 'frontend.account.register.page')).toBe(false);
    });

    test('reports the preselected shipping method when a new customer registers', () => {
        renderRegisterPage('Standard');

        submitRegistration();

        expect(shippingEvents()).toEqual([expect.objectContaining({
            currency: 'EUR',
            value: 20,
            shipping_tier: 'Standard',
            items: [expect.objectContaining({ item_id: 'SW1' })],
        })]);
    });

    test('ignores an invalid registration, other forms and a missing consent', () => {
        renderRegisterPage('Standard');

        submitRegistration(false);
        submitRegistration(true, document.createElement('form'));
        event.active = false;
        submitRegistration();

        expect(shippingEvents()).toEqual([]);
    });

    test('reports nothing for a cart without physical products, which renders no shipping method', () => {
        renderRegisterPage(null);

        submitRegistration();

        expect(shippingEvents()).toEqual([]);
    });

    test('is not reported again on the confirm page when the method stays the same', () => {
        renderRegisterPage('Standard');
        submitRegistration();

        renderConfirmPage('Standard');
        new AddShippingInfoEvent().execute();

        expect(shippingEvents().map(parameters => parameters.shipping_tier)).toEqual(['Standard']);
    });

    /**
     * After the registration the customer can get other shipping methods, for example through a rule
     * for a customer group, so the confirm page can preselect a different one than the register page.
     */
    test('is reported again on the confirm page when it shows a different method', () => {
        renderRegisterPage('Standard');
        submitRegistration();

        renderConfirmPage('Express');
        new AddShippingInfoEvent().execute();

        expect(shippingEvents().map(parameters => parameters.shipping_tier)).toEqual(['Standard', 'Express']);
    });

    test('a registration submitted twice is reported once', () => {
        renderRegisterPage('Standard');

        submitRegistration();
        submitRegistration();

        expect(shippingEvents()).toHaveLength(1);
    });
});
