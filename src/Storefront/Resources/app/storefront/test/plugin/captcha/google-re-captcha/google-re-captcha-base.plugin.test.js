import GoogleReCaptchaBasePlugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-base.plugin';

describe('GoogleReCaptchaBasePlugin tests', () => {
    let googleReCaptchaBasePlugin;
    let mockElement;
    let originalPluginManager;
    let mockRecaptchaScriptElement;

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
            ready: jest.fn(),
            render: jest.fn(),
            execute: jest.fn(),
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

            // formPluginInstances is a Map, the code uses `.values()` to iterate over plugin instances
            const instancesForAjaxTest = new Map([
                ['ajaxPlugin', mockAjaxPlugin],
                ['nonAjaxPlugin', mockNonAjaxPlugin],
                ['pluginWithoutMethod', mockPluginWithoutMethod],
            ]);

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

        test('iterating formPluginInstances.values() yields plugin objects, not [key, value] arrays (fix for #14045)', () => {
            // This test validates that using .values() correctly iterates over plugin instances
            // Before the fix, `for (const plugin of this.formPluginInstances)` yielded [key, value] arrays
            // which caused `typeof plugin.sendAjaxFormSubmit` to always be 'undefined'

            const iteratedPlugins = [];
            for (const plugin of googleReCaptchaBasePlugin.formPluginInstances.values()) {
                iteratedPlugins.push(plugin);
            }

            // Verify we get actual plugin objects, not arrays
            expect(iteratedPlugins).toHaveLength(3);
            expect(iteratedPlugins[0]).toBe(mockAjaxPlugin);
            expect(iteratedPlugins[1]).toBe(mockNonAjaxPlugin);
            expect(iteratedPlugins[2]).toBe(mockPluginWithoutMethod);

            // Verify sendAjaxFormSubmit is accessible as a function (not undefined like with arrays)
            expect(typeof iteratedPlugins[0].sendAjaxFormSubmit).toBe('function');
            expect(typeof iteratedPlugins[1].sendAjaxFormSubmit).toBe('function');
            expect(typeof iteratedPlugins[2].sendAjaxFormSubmit).toBe('undefined');
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

        test('_submitInvisibleForm calls sendAjaxFormSubmit on FormCmsHandler like any other AJAX plugin', () => {
            const mockFormCmsHandler = {
                sendAjaxFormSubmit: jest.fn(),
                options: {},
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
            expect(mockFormCmsHandler.sendAjaxFormSubmit).toHaveBeenCalledTimes(1);
            expect(cmsPlugin._form.submit).not.toHaveBeenCalled();
        });
    });


    describe('_onFormSubmitCallback', () => {
        beforeEach(() => {
            googleReCaptchaBasePlugin._executeGoogleReCaptchaInitialization();
            googleReCaptchaBasePlugin.onFormSubmit = jest.fn();
        });

        test('does not call onFormSubmit when form is already submitting', () => {
            googleReCaptchaBasePlugin._formSubmitting = true;
            const submitEvent = new Event('submit');

            googleReCaptchaBasePlugin._onFormSubmitCallback(submitEvent);

            expect(googleReCaptchaBasePlugin.onFormSubmit).not.toHaveBeenCalled();
        });

        test('prevents default and calls onFormSubmit when form is not submitting', () => {
            googleReCaptchaBasePlugin._formSubmitting = false;
            const submitEvent = new Event('submit');
            jest.spyOn(submitEvent, 'preventDefault');

            googleReCaptchaBasePlugin._onFormSubmitCallback(submitEvent);

            expect(submitEvent.preventDefault).toHaveBeenCalled();
            expect(googleReCaptchaBasePlugin.onFormSubmit).toHaveBeenCalled();
            expect(googleReCaptchaBasePlugin._formSubmitting).toBe(true);
        });
    });

    describe('_submitInvisibleForm validation', () => {
        beforeEach(() => {
            googleReCaptchaBasePlugin._executeGoogleReCaptchaInitialization();
            googleReCaptchaBasePlugin._form.submit = jest.fn();
        });

        test('does not submit when form validation fails', () => {
            googleReCaptchaBasePlugin._form.checkValidity = jest.fn(() => false);

            googleReCaptchaBasePlugin._submitInvisibleForm();

            expect(googleReCaptchaBasePlugin._form.submit).not.toHaveBeenCalled();
            expect(googleReCaptchaBasePlugin._formSubmitting).toBe(false);
        });
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
