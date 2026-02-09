import GoogleReCaptchaBasePlugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-base.plugin';

describe('GoogleReCaptchaBasePlugin tests', () => {
    let googleReCaptchaBasePlugin = undefined;

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
            ready: () => {},
            execute: () => {},
        };

        mockElement = createMockElement();
        document.body.appendChild(mockElement);

        mockRecaptchaScriptElement = document.createElement('script');
        mockRecaptchaScriptElement.id = 'recaptcha-script';
        document.body.appendChild(mockRecaptchaScriptElement);

        originalPluginManager = window.PluginManager;
        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn(() => new Map()),
            getPlugin: jest.fn(() => {
                return {
                    get: jest.fn((prop) => {
                        if (prop === 'instances') {
                            return [];
                        }
                        return undefined;
                    }),
                };
            }),
            initializePluginsInParentElement: jest.fn(),
        };

        googleReCaptchaBasePlugin = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });
    });

    afterEach(() => {
        googleReCaptchaBasePlugin = undefined;
    });

    test('GoogleReCaptchaBasePlugin exists', () => {
        expect(typeof googleReCaptchaBasePlugin).toBe('object');
    });

    test('Throw error if input field for Google reCAPTCHA is missing', () => {
        const mockForm = document.createElement('form');

        expect(() => new GoogleReCaptchaBasePlugin(mockForm)).toThrow(Error('Input field for Google reCAPTCHA is missing!'));

        // eslint-disable-next-line no-unused-vars
        const pluginWithScript = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });
        expect(script.getAttribute('src')).toBe('http://example.com/recaptcha.js');

        if (script.parentElement) {
            script.parentElement.removeChild(script);
        }
    });

    test('init sets global recaptcha script src attribute only once and calls grecaptcha.ready for each google-re-captcha plugin instance', () => {
        if (mockRecaptchaScriptElement?.parentElement) {
            mockRecaptchaScriptElement.parentElement.removeChild(mockRecaptchaScriptElement);
        }

        const script = document.createElement('script');
        script.id = 'recaptcha-script';
        script.setAttribute('data-src', 'http://example.com/recaptcha.js');
        document.body.appendChild(script);
        const setAttributeSpy = jest.spyOn(script, 'setAttribute');

        // Mock grecaptcha.ready to track how many times it was called
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

        // Should set the src attribute only once and should call grecaptcha.ready once per plugin instance
        expect(setAttributeSpy.mock.calls.filter(([attribute]) => attribute === 'src')).toHaveLength(1);
        expect(mockReady).toHaveBeenCalledTimes(3);

        if (script.parentElement) {
            script.parentElement.removeChild(script);
        }
    });

    test('init returns early if no recaptcha script element found', () => {
        if (mockRecaptchaScriptElement?.parentElement) {
            mockRecaptchaScriptElement.parentElement.removeChild(mockRecaptchaScriptElement);
        }

        // Mock grecaptcha.ready to track if it was called
        const mockReady = jest.fn();
        window.grecaptcha.ready = mockReady;

        // eslint-disable-next-line no-unused-vars
        const pluginWithoutScript = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

        // Should not call grecaptcha.ready since no script found
        expect(mockReady).not.toHaveBeenCalled();
    });

    test.each([
        ['grecaptcha is undefined', undefined],
        ['grecaptcha.ready is not a function', { ready: 'not-a-function' }],
    ])('init does not call grecaptcha.ready when %s', (_, grecaptchaValue) => {
        window.grecaptcha = grecaptchaValue;

        // Should not throw an error
        expect(() => {
            new GoogleReCaptchaBasePlugin(mockElement, {
                grecaptchaInputSelector: '.grecaptcha-input',
            });
        }).not.toThrow();
    });


    test('init does not proceed if no form is found during async init', () => {
        const divElement = document.createElement('div');
        const inputField = document.createElement('input');
        inputField.className = 'grecaptcha-input';
        mockForm.appendChild(inputField);

        googleReCaptchaBasePlugin = new GoogleReCaptchaBasePlugin(mockForm, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

        expect(typeof googleReCaptchaBasePlugin).toBe('object');
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


