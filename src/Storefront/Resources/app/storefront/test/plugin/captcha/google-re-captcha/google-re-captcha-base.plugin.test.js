import GoogleReCaptchaBasePlugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-base.plugin';

describe('GoogleReCaptchaBasePlugin tests', () => {
    let googleReCaptchaBasePlugin = undefined;
    let mockElement;
    let originalPluginManager;

    function createMockElement() {
        const form = document.createElement('form');
        const inputField = document.createElement('input');
        inputField.className = 'grecaptcha-input';
        form.appendChild(inputField);

        form.submit = jest.fn();
        form.checkValidity = jest.fn(() => true);

        return form;
    }

    beforeEach(() => {
        window.grecaptcha = {
            ready: (cb) => cb(),
            execute: jest.fn(),
        };

        mockElement = createMockElement();
        document.body.appendChild(mockElement);

        originalPluginManager = window.PluginManager;
        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn(() => new Map()),
            getPlugin: jest.fn(() => new Map([['instances', []]])),
        };

        googleReCaptchaBasePlugin = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });
    });

    afterEach(() => {
        window.PluginManager = originalPluginManager;
        googleReCaptchaBasePlugin = undefined;

        if (mockElement && mockElement.parentElement) {
            mockElement.parentElement.removeChild(mockElement);
        }
    });

    test('GoogleReCaptchaBasePlugin exists', () => {
        expect(typeof googleReCaptchaBasePlugin).toBe('object');
    });

    test('Throw error if input field for Google reCAPTCHA is missing', () => {
        const mockForm = document.createElement('form');
        document.body.appendChild(mockForm);

        expect(() => new GoogleReCaptchaBasePlugin(mockForm)).toThrow(Error('Input field for Google reCAPTCHA is missing!'));

        if (mockForm.parentElement) {
            mockForm.parentElement.removeChild(mockForm);
        }
    });

    test('init calls grecaptcha.ready for each plugin instance', () => {
        const mockReady = jest.fn();
        window.grecaptcha.ready = mockReady;

        new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

        const mockElement2 = createMockElement();
        document.body.appendChild(mockElement2);

        new GoogleReCaptchaBasePlugin(mockElement2, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

        const mockElement3 = createMockElement();
        document.body.appendChild(mockElement3);

        new GoogleReCaptchaBasePlugin(mockElement3, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

        expect(mockReady).toHaveBeenCalledTimes(3);

        if (mockElement2.parentElement) {
            mockElement2.parentElement.removeChild(mockElement2);
        }
        if (mockElement3.parentElement) {
            mockElement3.parentElement.removeChild(mockElement3);
        }
    });

    test.each([
        ['grecaptcha is undefined', undefined],
        ['grecaptcha.ready is not a function', { ready: 'not-a-function' }],
    ])('init does not call grecaptcha.ready when %s', (_, grecaptchaValue) => {
        window.grecaptcha = grecaptchaValue;

        expect(() => {
            new GoogleReCaptchaBasePlugin(mockElement, {
                grecaptchaInputSelector: '.grecaptcha-input',
            });
        }).not.toThrow();
    });

    test('init does not proceed if no form is found', () => {
        const divElement = document.createElement('div');
        const inputField = document.createElement('input');
        inputField.className = 'grecaptcha-input';
        divElement.appendChild(inputField);

        const plugin = new GoogleReCaptchaBasePlugin(divElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

        expect(plugin._form).toBeFalsy();
    });

    test('onFormSubmit is called _onFormSubmitCallback', () => {
        googleReCaptchaBasePlugin.onFormSubmit = jest.fn();

        googleReCaptchaBasePlugin._formSubmitting = true;

        const submitEvent = new Event('submit');

        googleReCaptchaBasePlugin._onFormSubmitCallback(submitEvent);

        expect(googleReCaptchaBasePlugin.onFormSubmit).not.toHaveBeenCalled();
        expect(googleReCaptchaBasePlugin._formSubmitting).toEqual(true);

        googleReCaptchaBasePlugin._formSubmitting = false;

        googleReCaptchaBasePlugin._onFormSubmitCallback(submitEvent);
        expect(googleReCaptchaBasePlugin.onFormSubmit).toHaveBeenCalled();
    });

    test('form is not submitted is not validated', () => {
        googleReCaptchaBasePlugin._form.submit = jest.fn();
        googleReCaptchaBasePlugin._form.checkValidity = () => { return false; };

        googleReCaptchaBasePlugin._submitInvisibleForm();

        expect(googleReCaptchaBasePlugin._form.submit).not.toHaveBeenCalled();

        googleReCaptchaBasePlugin._form.checkValidity = () => { return true; };

        googleReCaptchaBasePlugin._submitInvisibleForm();

        expect(googleReCaptchaBasePlugin._form.submit).toHaveBeenCalled();
    });
});
