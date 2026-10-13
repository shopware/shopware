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

    function submit(className) {
        const target = document.createElement('form');
        target.classList.add(className);
        // FormHandler publishes `validSubmit` on the form element once the form is valid
        event._onFormSubmit({ target });
    }

    test('reports a valid register form submit without an ecommerce object', () => {
        submit('register-form');

        expect(window.gtag).toHaveBeenCalledWith('event', 'sign_up', { method: 'mail' });
    });

    test('ignores other forms', () => {
        submit('login-form');

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('listens to the valid submit of the form handler', () => {
        expect(event.getPluginName()).toBe('FormHandler');
        expect(event.getEvents()).toHaveProperty('validSubmit');
    });

    test('does nothing without consent', () => {
        event.active = false;
        submit('register-form');

        expect(window.gtag).not.toHaveBeenCalled();
    });
});
