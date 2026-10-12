import LoginEvent from 'src/plugin/google-analytics/events/login.event';

describe('plugin/google-analytics/events/login.event', () => {
    let event;

    beforeEach(() => {
        window.gtag = jest.fn();
        window.gtagIsTagManager = false;
        event = new LoginEvent();
        event.active = true;
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    function submit(className, validity) {
        const target = document.createElement('form');
        target.classList.add(className);
        event._onFormSubmit({ target, detail: { validity } });
    }

    test('supports the login and the checkout register page', () => {
        expect(event.supports(null, null, 'frontend.account.login.page')).toBe(true);
        expect(event.supports(null, null, 'frontend.checkout.register.page')).toBe(true);
        expect(event.supports(null, null, 'frontend.home.page')).toBe(false);
    });

    test('reports a valid login form submit without an ecommerce object', () => {
        submit('login-form', true);

        expect(window.gtag).toHaveBeenCalledWith('event', 'login', { method: 'mail' });
    });

    test('ignores an invalid submit and other forms', () => {
        submit('login-form', false);
        submit('register-form', true);

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does nothing without consent', () => {
        event.active = false;
        submit('login-form', true);

        expect(window.gtag).not.toHaveBeenCalled();
    });
});
