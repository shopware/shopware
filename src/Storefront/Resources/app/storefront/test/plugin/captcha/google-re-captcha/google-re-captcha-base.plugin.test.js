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
            jest.spyOn(submitEvent, 'preventDefault');
            jest.spyOn(submitEvent, 'stopImmediatePropagation');

            googleReCaptchaBasePlugin._onFormSubmitCallback(submitEvent);

            expect(submitEvent.preventDefault).toHaveBeenCalled();
            expect(submitEvent.stopImmediatePropagation).toHaveBeenCalled();
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
