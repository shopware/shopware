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
        window.sessionStorage.clear();
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    function formEvent(className) {
        const form = document.createElement('form');
        form.classList.add(className);

        return { target: form };
    }

    function login() {
        new LoginEvent()._onFormSubmit(formEvent('login-form'));
        new LoginEvent().execute();
    }

    // the account register page sends a hidden `createCustomerAccount`, the checkout register page a checkbox
    function registerFormEvent(createCustomerAccount = '1') {
        const event = formEvent('register-form');

        if (createCustomerAccount !== null) {
            const input = document.createElement('input');
            input.name = 'createCustomerAccount';
            input.value = createCustomerAccount;
            event.target.appendChild(input);
        }

        return event;
    }

    function signUp() {
        new SignUpEvent()._onFormSubmit(registerFormEvent());
        new SignUpEvent().execute();
    }

    test('login reports the method through gtag on the page after the submit', () => {
        new LoginEvent()._onFormSubmit(formEvent('login-form'));

        expect(window.gtag).not.toHaveBeenCalled();

        new LoginEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'login', { method: 'mail' });
    });

    test('login is reported once', () => {
        login();
        new LoginEvent().execute();

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    // the server rejects wrong credentials after the submit and renders the login form again
    test('login reports nothing when the next page shows the login form with an error', () => {
        new LoginEvent()._onFormSubmit(formEvent('login-form'));
        document.body.innerHTML = '<form class="login-form"><input name="password" class="form-control is-invalid"></form>';

        new LoginEvent().execute();
        document.body.innerHTML = '';
        new LoginEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('login reports nothing on a page without a submitted login', () => {
        new LoginEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('login is checked on every page, because the shopper can land anywhere', () => {
        expect(new LoginEvent().supports(null, null, 'frontend.home.page')).toBe(true);
    });

    test('sign_up reports the method through gtag on the page after the submit', () => {
        new SignUpEvent()._onFormSubmit(registerFormEvent());

        expect(window.gtag).not.toHaveBeenCalled();

        new SignUpEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'sign_up', { method: 'mail' });
    });

    test('sign_up is reported once', () => {
        signUp();
        new SignUpEvent().execute();

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    test('sign_up reports the checked "Create customer account" checkbox of the checkout', () => {
        new SignUpEvent()._onFormSubmit(registerFormEvent('true'));
        new SignUpEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'sign_up', { method: 'mail' });
    });

    // an unchecked checkbox is not part of the form data
    test('sign_up reports nothing for a guest checkout', () => {
        new SignUpEvent()._onFormSubmit(registerFormEvent(null));
        new SignUpEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    // the server rejects the registration after the submit, for example for a registered email, and renders the form again
    test('sign_up reports nothing when the next page shows the register form with an error', () => {
        new SignUpEvent()._onFormSubmit(registerFormEvent());
        document.body.innerHTML = '<form class="register-form"><input name="email" class="form-control is-invalid"></form>';

        new SignUpEvent().execute();
        document.body.innerHTML = '';
        new SignUpEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('sign_up is checked on every page, because the shopper can land anywhere', () => {
        expect(new SignUpEvent().supports(null, null, 'frontend.checkout.confirm.page')).toBe(true);
    });

    test('search reports the search term through gtag', () => {
        new SearchAjaxEvent()._onSearch({ detail: { value: 'shirt' } });

        expect(window.gtag).toHaveBeenCalledWith('event', 'search', { search_term: 'shirt' });
    });

    // events without ecommerce data keep their parameters at the top level of the data layer
    test('pushes events without ecommerce data flat to the Tag Manager data layer', () => {
        window.gtagIsTagManager = true;

        login();
        signUp();
        new SearchAjaxEvent()._onSearch({ detail: { value: 'shirt' } });

        expect(window.dataLayer).toEqual([
            { event: 'login', method: 'mail' },
            { event: 'sign_up', method: 'mail' },
            { event: 'search', search_term: 'shirt' },
        ]);
        expect(window.gtag).not.toHaveBeenCalled();
    });

    // FormHandler only publishes `validSubmit` for a valid form, so an invalid form reports nothing
    test.each([
        ['login', LoginEvent],
        ['sign_up', SignUpEvent],
    ])('%s listens to the valid submit of the form handler', (name, EventClass) => {
        const event = new EventClass();

        expect(event.getPluginName()).toBe('FormHandler');
        expect(Object.keys(event.getEvents())).toEqual(['validSubmit']);
    });

    test.each([
        ['login', LoginEvent],
        ['sign_up', SignUpEvent],
    ])('%s ignores foreign forms', (name, EventClass) => {
        new EventClass()._onFormSubmit(formEvent('newsletter-form'));
        new EventClass().execute?.();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('reports nothing while disabled', () => {
        const events = [new LoginEvent(), new SignUpEvent(), new SearchAjaxEvent()];
        events.forEach(event => event.disable());

        events[0]._onFormSubmit(formEvent('login-form'));
        events[0].execute();
        events[1]._onFormSubmit(registerFormEvent());
        events[1].execute();
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
