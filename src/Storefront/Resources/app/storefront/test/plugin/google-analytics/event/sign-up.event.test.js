import SignUpEvent from 'src/plugin/google-analytics/events/sign-up.event';

describe('plugin/google-analytics/events/sign-up.event', () => {
    let event;

    beforeEach(() => {
        window.gtag = jest.fn();
        window.gtagIsTagManager = false;
        event = new SignUpEvent();
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

    test('reports a valid register form submit without an ecommerce object', () => {
        submit('register-form', true);

        expect(window.gtag).toHaveBeenCalledWith('event', 'sign_up', { method: 'mail' });
    });

    test('ignores an invalid submit and other forms', () => {
        submit('register-form', false);
        submit('login-form', true);

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does nothing without consent', () => {
        event.active = false;
        submit('register-form', true);

        expect(window.gtag).not.toHaveBeenCalled();
    });
});
