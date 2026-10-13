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

    function submit(className) {
        const target = document.createElement('form');
        target.classList.add(className);
        // FormHandler publishes `validSubmit` on the form element once the form is valid
        event._onFormSubmit({ target });
    }

    test('supports the login and the checkout register page', () => {
        expect(event.supports(null, null, 'frontend.account.login.page')).toBe(true);
        expect(event.supports(null, null, 'frontend.checkout.register.page')).toBe(true);
        expect(event.supports(null, null, 'frontend.home.page')).toBe(false);
    });

    test('reports a valid login form submit without an ecommerce object', () => {
        submit('login-form');

        expect(window.gtag).toHaveBeenCalledWith('event', 'login', { method: 'mail' });
    });

    test('ignores other forms', () => {
        submit('register-form');

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('listens to the valid submit of the form handler', () => {
        expect(event.getPluginName()).toBe('FormHandler');
        expect(event.getEvents()).toHaveProperty('validSubmit');
    });

    test('does nothing without consent', () => {
        event.active = false;
        submit('login-form');

        expect(window.gtag).not.toHaveBeenCalled();
    });
});
