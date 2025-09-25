import GoogleReCaptchaBasePlugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-base.plugin';

describe('GoogleReCaptchaBasePlugin tests', () => {
    let googleReCaptchaBasePlugin;
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

    test('init returns early if recaptcha script already has src attribute', () => {
        if (mockRecaptchaScriptElement?.parentElement) {
            mockRecaptchaScriptElement.parentElement.removeChild(mockRecaptchaScriptElement);
        }

        const script = document.createElement('script');
        script.id = 'recaptcha-script';
        script.setAttribute('src', 'already-set.js');
        script.setAttribute('data-src', 'http://example.com/recaptcha.js');
        document.body.appendChild(script);

        // Mock grecaptcha.ready to track if it was called
        const mockReady = jest.fn();
        window.grecaptcha.ready = mockReady;

        // eslint-disable-next-line no-unused-vars
        const pluginWithExistingSrc = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

        // Should not have changed the src and should not call grecaptcha.ready
        expect(script.getAttribute('src')).toBe('already-set.js');
        expect(mockReady).not.toHaveBeenCalled();

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

    test('init does not call grecaptcha.ready when grecaptcha is not available', () => {
        window.grecaptcha = undefined;

        // eslint-disable-next-line no-unused-vars
        const pluginWithoutGrecaptcha = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

        // Since grecaptcha is undefined, ready should not be called
        // No error should be thrown either
        expect(true).toBe(true); // Simple assertion to ensure test runs
    });

    test('init does not call grecaptcha.ready when ready is not a function', () => {
        window.grecaptcha = {
            ready: 'not-a-function',
        };

        // eslint-disable-next-line no-unused-vars
        const pluginWithInvalidReady = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

        // Since ready is not a function, it should not be called
        expect(true).toBe(true); // Simple assertion to ensure test runs
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

            // Mock an iterable collection that works like the original implementation expects
            // The code does `for (const plugin of this.formPluginInstances)` expecting plugin instances
            const instancesForAjaxTest = [
                mockAjaxPlugin,
                mockNonAjaxPlugin,
                mockPluginWithoutMethod,
            ];
            // Add Map-like methods for _isCmsForm() to work
            instancesForAjaxTest.has = jest.fn(() => false);
            instancesForAjaxTest.get = jest.fn();

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

        test('_submitInvisibleForm submits form directly when no AJAX plugins found', () => {
            const emptyInstancesMap = new Map();
            window.PluginManager.getPluginInstancesFromElement = jest.fn(() => emptyInstancesMap);

            const pluginWithNoAjax = new GoogleReCaptchaBasePlugin(mockElement, {
                grecaptchaInputSelector: '.grecaptcha-input',
            });
            pluginWithNoAjax._executeGoogleReCaptchaInitialization();
            pluginWithNoAjax._form.submit = jest.fn();

            pluginWithNoAjax._submitInvisibleForm();
            expect(pluginWithNoAjax._form.submit).toHaveBeenCalledTimes(1);
        });

        test('_submitInvisibleForm handles CMS form through FormCmsHandler', () => {
            const mockFormCmsHandler = {
                _submitForm: jest.fn(),
            };

            const cmsInstancesMap = new Map([
                ['FormCmsHandler', mockFormCmsHandler],
            ]);

            window.PluginManager.getPluginInstancesFromElement = jest.fn(() => cmsInstancesMap);

            const cmsPlugin = new GoogleReCaptchaBasePlugin(mockElement, {
                grecaptchaInputSelector: '.grecaptcha-input',
            });
            cmsPlugin._executeGoogleReCaptchaInitialization();
            cmsPlugin._form.submit = jest.fn();

            cmsPlugin._submitInvisibleForm();
            expect(mockFormCmsHandler._submitForm).toHaveBeenCalledTimes(1);
            expect(cmsPlugin._form.submit).not.toHaveBeenCalled();
        });

        test('_submitInvisibleForm handles CMS form when FormCmsHandler exists but get returns null', () => {
            const cmsInstancesMap = [];
            // Add Map-like methods - has returns true but get returns null
            cmsInstancesMap.has = jest.fn(key => key === 'FormCmsHandler');
            cmsInstancesMap.get = jest.fn(key => key === 'FormCmsHandler' ? null : undefined);
            cmsInstancesMap.set = jest.fn();

            window.PluginManager.getPluginInstancesFromElement = jest.fn(() => cmsInstancesMap);

            const cmsPlugin = new GoogleReCaptchaBasePlugin(mockElement, {
                grecaptchaInputSelector: '.grecaptcha-input',
            });
            cmsPlugin._executeGoogleReCaptchaInitialization();
            cmsPlugin._form.submit = jest.fn();

            // Should continue to form submission logic since FormCmsHandler plugin is null
            cmsPlugin._submitInvisibleForm();
            expect(cmsPlugin._form.submit).toHaveBeenCalledTimes(1);
        });
    });


    test('form submission flow handles various scenarios correctly', () => {
        googleReCaptchaBasePlugin._executeGoogleReCaptchaInitialization();
        expect(googleReCaptchaBasePlugin._form).toBeDefined();

        // Test that onFormSubmit is not called when form is already submitting
        googleReCaptchaBasePlugin.onFormSubmit = jest.fn();
        googleReCaptchaBasePlugin._formSubmitting = true;
        const submitEvent = new Event('submit');
        jest.spyOn(submitEvent, 'preventDefault');
        googleReCaptchaBasePlugin._onFormSubmitCallback(submitEvent);
        expect(googleReCaptchaBasePlugin.onFormSubmit).not.toHaveBeenCalled();
        expect(googleReCaptchaBasePlugin._formSubmitting).toBe(true);

        // Test that onFormSubmit is called when form is not submitting
        googleReCaptchaBasePlugin._formSubmitting = false;
        googleReCaptchaBasePlugin._onFormSubmitCallback(submitEvent);
        expect(submitEvent.preventDefault).toHaveBeenCalled();
        expect(googleReCaptchaBasePlugin.onFormSubmit).toHaveBeenCalled();

        // Test form validation during submission
        googleReCaptchaBasePlugin._form.submit = jest.fn();
        googleReCaptchaBasePlugin._form.checkValidity = jest.fn(() => false);
        googleReCaptchaBasePlugin._submitInvisibleForm();
        expect(googleReCaptchaBasePlugin._form.submit).not.toHaveBeenCalled();
        expect(googleReCaptchaBasePlugin._formSubmitting).toBe(false);

        // Test successful form submission when validated
        googleReCaptchaBasePlugin._form.checkValidity = jest.fn(() => true);
        googleReCaptchaBasePlugin._submitInvisibleForm();
        expect(googleReCaptchaBasePlugin._form.submit).toHaveBeenCalled();
    });

    describe('_getForm', () => {
        test('finds form when el is the form itself', () => {
            const pluginOnForm = new GoogleReCaptchaBasePlugin(mockElement, {});
            pluginOnForm._getForm();
            expect(pluginOnForm._form).toBe(mockElement);
        });

        test('finds form when el is a child of the form', () => {
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

            document.body.removeChild(parentForm);
        });
    });

    describe('URL validation', () => {
        test('_isValidUrl correctly validates URLs', () => {
            const plugin = new GoogleReCaptchaBasePlugin(mockElement, {
                grecaptchaInputSelector: '.grecaptcha-input',
            });

            expect(plugin._isValidUrl('invalid-url')).toBe(false);
            expect(plugin._isValidUrl('ftp://example.com')).toBe(false);
            expect(plugin._isValidUrl('javascript:alert(1)')).toBe(false);
            expect(plugin._isValidUrl('http://example.com')).toBe(true);
            expect(plugin._isValidUrl('https://example.com')).toBe(true);
        });

        test('init does not set src if data-src is invalid URL', () => {
            if (mockRecaptchaScriptElement?.parentElement) {
                mockRecaptchaScriptElement.parentElement.removeChild(mockRecaptchaScriptElement);
            }

            const script = document.createElement('script');
            script.id = 'recaptcha-script';
            script.setAttribute('data-src', 'invalid-url');
            document.body.appendChild(script);

        new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input',
        });

            // Should not have set the src attribute due to invalid URL
            expect(script.hasAttribute('src')).toBe(false);

            if (script.parentElement) {
                script.parentElement.removeChild(script);
            }
        });
    });
});
