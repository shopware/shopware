import GoogleReCaptchaBasePlugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-base.plugin';

describe('GoogleReCaptchaBasePlugin tests', () => {
    let googleReCaptchaBasePlugin = undefined;
    let mockElement;
    let originalPluginManager;
    let mockRecaptchaScriptElement;

    beforeEach(() => {
        window.grecaptcha = {
            ready: jest.fn(),
            render: jest.fn(),
            execute: jest.fn(),
        };

        mockElement = document.createElement('form');
        const inputField = document.createElement('input');
        inputField.className = 'grecaptcha-input';
        mockElement.appendChild(inputField);

        mockElement.submit = jest.fn();
        mockElement.checkValidity = jest.fn(() => true);

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
        };

        googleReCaptchaBasePlugin = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });
    });

    afterEach(() => {
        window.grecaptcha = undefined;
        document.body.removeChild(mockElement);
        if (mockRecaptchaScriptElement?.parentElement) {
            mockRecaptchaScriptElement.parentElement.removeChild(mockRecaptchaScriptElement);
        }
        window.PluginManager = originalPluginManager;
    });

    test('GoogleReCaptchaBasePlugin exists and init calls grecaptcha.ready', () => {
        expect(typeof googleReCaptchaBasePlugin).toBe('object');
        expect(window.grecaptcha.ready).toHaveBeenCalledTimes(1);
    });

    test('init sets src on recaptcha script if data-src exists and src is missing', () => {
        if (mockRecaptchaScriptElement?.parentElement) {
            mockRecaptchaScriptElement.parentElement.removeChild(mockRecaptchaScriptElement);
        }

        const script = document.createElement('script');
        script.id = 'recaptcha-script';
        script.setAttribute('data-src', 'http://example.com/recaptcha.js');
        document.body.appendChild(script);

        // eslint-disable-next-line no-unused-vars
        const pluginWithScript = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });
        expect(script.getAttribute('src')).toBe('http://example.com/recaptcha.js');

        if (script.parentElement) {
            script.parentElement.removeChild(script);
        }
    });


    test('init does not proceed if no form is found during async init', () => {
        const divElement = document.createElement('div');
        const inputField = document.createElement('input');
        inputField.className = 'no-form-grecaptcha-input';
        divElement.appendChild(inputField);
        document.body.appendChild(divElement);

        let noFormPluginReadyCallback;
        window.grecaptcha.ready = jest.fn(cb => {
            noFormPluginReadyCallback = cb;
        });

        const noFormPlugin = new GoogleReCaptchaBasePlugin(divElement, {
            grecaptchaInputSelector: '.no-form-grecaptcha-input',
        });

        expect(noFormPluginReadyCallback).toBeDefined();
        noFormPluginReadyCallback.call(noFormPlugin);

        expect(noFormPlugin.grecaptchaInput).toBeUndefined();

        document.body.removeChild(divElement);
    });


    test('init throws error if grecaptcha render/execute methods are missing during async init', () => {
        let errorReadyCallback;
        window.grecaptcha = {
            ready: jest.fn(cb => {
                errorReadyCallback = cb;
            }),
        };

        const pluginForError = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

        expect(errorReadyCallback).toBeDefined();
        expect(() => errorReadyCallback.call(pluginForError)).toThrow('Google reCAPTCHA object (window.grecaptcha) methods (render/execute) not available.');
    });


    test('Throw error if input field for Google reCAPTCHA is missing during async init', () => {
        const mockFormError = document.createElement('form');
        document.body.appendChild(mockFormError);

        let errorPluginReadyCallback;
        window.grecaptcha.ready = jest.fn(cb => {
            errorPluginReadyCallback = cb;
        });
        const errorPlugin = new GoogleReCaptchaBasePlugin(mockFormError, {
            grecaptchaInputSelector: '.selector-that-does-not-exist',
        });
        expect(errorPluginReadyCallback).toBeDefined();
        expect(() => errorPluginReadyCallback.call(errorPlugin)).toThrow('Input field for Google reCAPTCHA is missing!');

        document.body.removeChild(mockFormError);
    });

    describe('AJAX form submission handling', () => {
        let mockAjaxPlugin;
        let mockNonAjaxPlugin;
        let mockPluginWithoutMethod;
        let specificPluginManagerMock; // To hold the mock for this describe block

        beforeEach(() => {
            mockAjaxPlugin = {
                sendAjaxFormSubmit: jest.fn(),
                options: { useAjax: true },
                formSubmittedByCaptcha: false,
            };
            mockNonAjaxPlugin = {
                sendAjaxFormSubmit: jest.fn(),
                options: { useAjax: false },
            };
            mockPluginWithoutMethod = {
                options: { useAjax: true },
            };

            const instancesForAjaxTest = [
                mockAjaxPlugin,
                mockNonAjaxPlugin,
                mockPluginWithoutMethod,
            ];

            specificPluginManagerMock = {
                getPluginInstancesFromElement: jest.fn(() => instancesForAjaxTest),
                getPlugin: jest.fn((pluginName) => {
                    return {
                        get: jest.fn((prop) => {
                            if (prop === 'instances') {
                                return [];
                            }
                            return undefined;
                        }),
                        _name: pluginName,
                    };
                }),
            };
            window.PluginManager = specificPluginManagerMock;

            googleReCaptchaBasePlugin._executeGoogleReCaptchaInitialization();
        });

        test('_setGoogleReCaptchaHandleSubmit sets flag on AJAX plugins', () => {
            expect(mockAjaxPlugin.formSubmittedByCaptcha).toBe(true);
            expect(mockNonAjaxPlugin.formSubmittedByCaptcha).toBeUndefined();
        });

        test('_submitInvisibleForm calls sendAjaxFormSubmit on AJAX plugins and does not submit form', () => {
            googleReCaptchaBasePlugin._form.submit = jest.fn();
            googleReCaptchaBasePlugin._submitInvisibleForm();
            expect(mockAjaxPlugin.sendAjaxFormSubmit).toHaveBeenCalledTimes(1);
            expect(mockNonAjaxPlugin.sendAjaxFormSubmit).not.toHaveBeenCalled();
            expect(googleReCaptchaBasePlugin._form.submit).not.toHaveBeenCalled();
        });
    });


    test('onFormSubmit is called _onFormSubmitCallback after async init', () => {
        googleReCaptchaBasePlugin._executeGoogleReCaptchaInitialization();
        expect(googleReCaptchaBasePlugin._form).toBeDefined();

        googleReCaptchaBasePlugin.onFormSubmit = jest.fn();
        googleReCaptchaBasePlugin._formSubmitting = true;
        const submitEvent = new Event('submit');
        jest.spyOn(submitEvent, 'preventDefault');
        googleReCaptchaBasePlugin._onFormSubmitCallback(submitEvent);
        expect(googleReCaptchaBasePlugin.onFormSubmit).not.toHaveBeenCalled();
        expect(googleReCaptchaBasePlugin._formSubmitting).toBe(true);
        googleReCaptchaBasePlugin._formSubmitting = false;
        googleReCaptchaBasePlugin._onFormSubmitCallback(submitEvent);
        expect(submitEvent.preventDefault).toHaveBeenCalled();
        expect(googleReCaptchaBasePlugin.onFormSubmit).toHaveBeenCalled();
    });

    test('form is not submitted if not validated, after async init', () => {
        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn(() => new Map()),
            getPlugin: jest.fn(() => {
                return {
                    get: jest.fn((prop) => {
                        if (prop === 'instances') return [];
                        return undefined;
                    }),
                };
            }),
        };

        const testPlugin = new GoogleReCaptchaBasePlugin(mockElement, { grecaptchaInputSelector: '.grecaptcha-input' });
        testPlugin._executeGoogleReCaptchaInitialization();

        expect(testPlugin._form).toBeDefined();
        testPlugin._form.submit = jest.fn();

        testPlugin._form.checkValidity = jest.fn(() => false);
        testPlugin._submitInvisibleForm();
        expect(testPlugin._form.submit).not.toHaveBeenCalled();
        expect(testPlugin._formSubmitting).toBe(false);

        testPlugin._form.checkValidity = jest.fn(() => true);
        testPlugin._submitInvisibleForm();
        expect(testPlugin._form.submit).toHaveBeenCalled();
    });

    test('_getForm finds form when el is the form itself', () => {
        const pluginOnForm = new GoogleReCaptchaBasePlugin(mockElement, {});
        pluginOnForm._getForm();
        expect(pluginOnForm._form).toBe(mockElement);
    });

    test('_getForm finds form when el is a child of the form', () => {
        const parentForm = document.createElement('form');
        const childDiv = document.createElement('div');
        const input = document.createElement('input');
        input.className = 'child-grecaptcha-input';
        childDiv.appendChild(input);
        parentForm.appendChild(childDiv);
        document.body.appendChild(parentForm);

        const pluginWithChildEl = new GoogleReCaptchaBasePlugin(childDiv, { grecaptchaInputSelector: '.child-grecaptcha-input'});
        pluginWithChildEl._getForm();
        expect(pluginWithChildEl._form).toBe(parentForm);
    });
});
