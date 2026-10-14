import CheckoutProgressEvent from 'src/plugin/google-analytics/events/checkout-progress.event';
import LoginEvent from 'src/plugin/google-analytics/events/login.event';
import SearchAjaxEvent from 'src/plugin/google-analytics/events/search-ajax.event';
import SignUpEvent from 'src/plugin/google-analytics/events/sign-up.event';

describe('plugin/google-analytics/events without ecommerce data', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
        window.dataLayer = [];
        window.gtagIsTagManager = false;
    });

    afterEach(() => {
        delete window.gtagIsTagManager;
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    function formEvent(className, validity = true) {
        const form = document.createElement('form');
        form.classList.add(className);

        return { target: form, detail: { validity } };
    }

    test('login reports the method through gtag', () => {
        new LoginEvent()._onFormSubmit(formEvent('login-form'));

        expect(window.gtag).toHaveBeenCalledWith('event', 'login', { method: 'mail' });
    });

    test('sign_up reports the method through gtag', () => {
        new SignUpEvent()._onFormSubmit(formEvent('register-form'));

        expect(window.gtag).toHaveBeenCalledWith('event', 'sign_up', { method: 'mail' });
    });

    test('search reports the search term through gtag', () => {
        new SearchAjaxEvent()._onSearch({ detail: { value: 'shirt' } });

        expect(window.gtag).toHaveBeenCalledWith('event', 'search', { search_term: 'shirt' });
    });

    // events without ecommerce data keep their parameters at the top level of the data layer
    test('pushes events without ecommerce data flat to the Tag Manager data layer', () => {
        window.gtagIsTagManager = true;

        new LoginEvent()._onFormSubmit(formEvent('login-form'));
        new SignUpEvent()._onFormSubmit(formEvent('register-form'));
        new SearchAjaxEvent()._onSearch({ detail: { value: 'shirt' } });

        expect(window.dataLayer).toEqual([
            { event: 'login', method: 'mail' },
            { event: 'sign_up', method: 'mail' },
            { event: 'search', search_term: 'shirt' },
        ]);
        expect(window.gtag).not.toHaveBeenCalled();
    });

    test.each([
        ['login', LoginEvent, 'login-form'],
        ['sign_up', SignUpEvent, 'register-form'],
    ])('%s ignores invalid and foreign forms', (name, EventClass, className) => {
        const event = new EventClass();

        event._onFormSubmit(formEvent(className, false));
        event._onFormSubmit(formEvent('newsletter-form'));

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('reports nothing while disabled', () => {
        const events = [new LoginEvent(), new SignUpEvent(), new SearchAjaxEvent()];
        events.forEach(event => event.disable());

        events[0]._onFormSubmit(formEvent('login-form'));
        events[1]._onFormSubmit(formEvent('register-form'));
        events[2]._onSearch({ detail: { value: 'shirt' } });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('the deprecated checkout_progress reports the line items as ecommerce data', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="20">
                <span class="hidden-line-item" data-id="SW10000" data-name="Shirt" data-quantity="1" data-price="20"></span>
            </div>
        `;

        new CheckoutProgressEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'checkout_progress', {
            items: [expect.objectContaining({ item_id: 'SW10000', item_name: 'Shirt', quantity: 1, price: 20 })],
        });
    });
});
