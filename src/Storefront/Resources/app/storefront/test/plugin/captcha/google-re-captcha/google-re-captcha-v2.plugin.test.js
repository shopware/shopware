import GoogleReCaptchaV2Plugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-v2.plugin';

describe('GoogleReCaptchaV2Plugin tests', () => {
    let googleReCaptchav2Plugin;
    let mockElement;
    let inputField;
    let captchaContainer;
    let mockIframe;
    let mockRecaptchaScriptElement;
    let consoleErrorSpy;

    beforeEach(() => {
        consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();

        window.grecaptcha = {
            ready: jest.fn((callback) => callback()),
            execute: jest.fn(() => Promise.resolve('mockExecuteToken')),
            render: jest.fn(() => 'mockWidgetId'),
            reset: jest.fn(),
        };

        mockElement = document.createElement('form');
        mockElement.submit = jest.fn();
        mockElement.checkValidity = jest.fn(() => true);

        inputField = document.createElement('input');
        inputField.className = 'grecaptcha-v2-input';

        captchaContainer = document.createElement('div');
        captchaContainer.className = 'grecaptcha-v2-container';

        mockIframe = document.createElement('iframe');
        mockElement.appendChild(mockIframe);

        mockRecaptchaScriptElement = document.createElement('script');
        mockRecaptchaScriptElement.id = 'recaptcha-script';
        document.body.appendChild(mockRecaptchaScriptElement);

        mockElement.appendChild(inputField);
        mockElement.appendChild(captchaContainer);
        document.body.appendChild(mockElement);

        googleReCaptchav2Plugin = new GoogleReCaptchaV2Plugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-v2-input',
            checkboxContainer: '.grecaptcha-v2-container',
            siteKey: 'test-site-key-v2',
            invisible: false,
            grecaptchaIframeHasErrorClassSelector: 'has-error',
        });
    });

    afterEach(() => {
        googleReCaptchav2Plugin = undefined;
        if (mockElement?.parentElement) {
            mockElement.parentElement.removeChild(mockElement);
        }
        if (mockRecaptchaScriptElement?.parentElement) {
            mockRecaptchaScriptElement.parentElement.removeChild(mockRecaptchaScriptElement);
        }
        window.grecaptcha = undefined;
        mockIframe = null;
        consoleErrorSpy.mockRestore();
    });

    test('initializes correctly with required properties', () => {
        expect(typeof googleReCaptchav2Plugin).toBe('object');
        expect(googleReCaptchav2Plugin.grecaptchaInput).toBeDefined();
        expect(googleReCaptchav2Plugin.grecaptcha).toBeDefined();
        expect(googleReCaptchav2Plugin.grecaptchaContainerIframe).toBe(mockIframe);
        expect(googleReCaptchav2Plugin.grecaptchaWidgetId).toEqual('mockWidgetId');
    });

    test('renders captcha with correct configuration', () => {
        expect(window.grecaptcha.render).toHaveBeenCalledWith(
            googleReCaptchav2Plugin.grecaptchaContainer,
            expect.objectContaining({
                sitekey: 'test-site-key-v2',
                size: 'normal',
                callback: expect.any(Function),
                'expired-callback': expect.any(Function),
                'error-callback': expect.any(Function),
            })
        );
    });

    test('handles error when grecaptcha is not available', () => {
        // Reset console error spy
        consoleErrorSpy.mockClear();

        // Spy on the parent class method to prevent it from throwing
        const parentClassMethod = jest.spyOn(Object.getPrototypeOf(Object.getPrototypeOf(googleReCaptchav2Plugin)), '_executeGoogleReCaptchaInitialization')
            .mockImplementation(function() {
                // Simulate what the parent method would do without throwing
                this._getForm();
                this.grecaptchaInput = this.el.querySelector(this.options.grecaptchaInputSelector);
                this.grecaptcha = null; // This is the key - set grecaptcha to null
                this._formSubmitting = false;
            });

        // Call the V2 implementation which should log the error
        googleReCaptchav2Plugin._executeGoogleReCaptchaInitialization();

        expect(consoleErrorSpy).toHaveBeenCalledWith('GoogleReCaptchaV2Plugin: Cannot render V2 captcha.');

        // Restore the spy
        parentClassMethod.mockRestore();
    });

    describe('form submission behavior', () => {
        beforeEach(() => {
            googleReCaptchav2Plugin._submitInvisibleForm = jest.fn();
        });

        test('handles visible captcha form submission with valid token', () => {
            googleReCaptchav2Plugin.options.invisible = false;
            googleReCaptchav2Plugin.grecaptchaInput.value = 'valid-token';

            googleReCaptchav2Plugin.onFormSubmit();

            expect(googleReCaptchav2Plugin._submitInvisibleForm).toHaveBeenCalled();
            expect(mockIframe.classList.contains('has-error')).toBe(false);
        });

        test('handles visible captcha form submission without token', () => {
            googleReCaptchav2Plugin.options.invisible = false;
            googleReCaptchav2Plugin.grecaptchaInput.value = '';

            googleReCaptchav2Plugin.onFormSubmit();

            expect(googleReCaptchav2Plugin._submitInvisibleForm).not.toHaveBeenCalled();
            expect(mockIframe.classList.contains('has-error')).toBe(true);
        });

        test('handles invisible captcha submission with widget id', () => {
            googleReCaptchav2Plugin.options.invisible = true;
            googleReCaptchav2Plugin.grecaptchaWidgetId = 'test-widget-id';
            window.grecaptcha.execute = jest.fn(() => Promise.resolve());

            googleReCaptchav2Plugin.onFormSubmit();

            expect(window.grecaptcha.execute).toHaveBeenCalledWith('test-widget-id');
        });

        test('handles invisible captcha submission without widget id', () => {
            googleReCaptchav2Plugin.options.invisible = true;
            googleReCaptchav2Plugin.grecaptchaWidgetId = null;

            googleReCaptchav2Plugin.onFormSubmit();

            expect(googleReCaptchav2Plugin._submitInvisibleForm).not.toHaveBeenCalled();
        });

        test('handles invisible captcha with existing valid token', async () => {
            googleReCaptchav2Plugin.options.invisible = true;
            googleReCaptchav2Plugin.grecaptchaWidgetId = 'test-widget-id';
            googleReCaptchav2Plugin.currentToken = 'existing-token';
            googleReCaptchav2Plugin.grecaptchaInput.value = 'existing-token';

            window.grecaptcha.execute = jest.fn(() => Promise.resolve());

            googleReCaptchav2Plugin.onFormSubmit();

            // Wait for the promise to resolve
            await new Promise(resolve => setTimeout(resolve, 0));

            expect(googleReCaptchav2Plugin._submitInvisibleForm).toHaveBeenCalled();
        });
    });

    describe('captcha callbacks', () => {
        beforeEach(() => {
            googleReCaptchav2Plugin._submitInvisibleForm = jest.fn();
            googleReCaptchav2Plugin.$emitter = {
                publish: jest.fn(),
            };
        });

        test('handles token response for visible captcha', () => {
            googleReCaptchav2Plugin.options.invisible = false;
            googleReCaptchav2Plugin.grecaptchaContainerIframe = mockIframe;
            mockIframe.classList.add('has-error');

            googleReCaptchav2Plugin._onCaptchaTokenResponse('test-token');

            expect(googleReCaptchav2Plugin.grecaptchaInput.value).toBe('test-token');
            expect(googleReCaptchav2Plugin.currentToken).toBe('test-token');
            expect(mockIframe.classList.contains('has-error')).toBe(false);
            expect(googleReCaptchav2Plugin._submitInvisibleForm).not.toHaveBeenCalled();
            expect(googleReCaptchav2Plugin.$emitter.publish).toHaveBeenCalledWith('onGreCaptchaTokenResponse', {
                info: { version: 'GoogleReCaptchaV2', invisible: false },
                token: 'test-token',
            });
        });

        test('handles token response for invisible captcha', () => {
            googleReCaptchav2Plugin.options.invisible = true;

            googleReCaptchav2Plugin._onCaptchaTokenResponse('test-token');

            expect(googleReCaptchav2Plugin.grecaptchaInput.value).toBe('test-token');
            expect(googleReCaptchav2Plugin.currentToken).toBe('test-token');
            expect(googleReCaptchav2Plugin._submitInvisibleForm).toHaveBeenCalled();
        });

        test('handles captcha expiration', () => {
            googleReCaptchav2Plugin.grecaptchaWidgetId = 'test-widget-id';
            googleReCaptchav2Plugin.grecaptchaInput.value = 'old-token';

            googleReCaptchav2Plugin._onGreCaptchaExpire();

            expect(window.grecaptcha.reset).toHaveBeenCalledWith('test-widget-id');
            expect(googleReCaptchav2Plugin.grecaptchaInput.value).toBe('');
            expect(googleReCaptchav2Plugin.$emitter.publish).toHaveBeenCalledWith('onGreCaptchaExpire', {
                info: { version: 'GoogleReCaptchaV2', invisible: false },
            });
        });

        test('handles captcha error', () => {
            googleReCaptchav2Plugin._onGreCaptchaError();

            expect(googleReCaptchav2Plugin.$emitter.publish).toHaveBeenCalledWith('onGreCaptchaError', {
                info: { version: 'GoogleReCaptchaV2', invisible: false },
            });
        });
    });

    test('provides correct captcha info', () => {
        const info = googleReCaptchav2Plugin.getGreCaptchaInfo();

        expect(info).toEqual({
            version: 'GoogleReCaptchaV2',
            invisible: false,
        });

        googleReCaptchav2Plugin.options.invisible = true;
        const invisibleInfo = googleReCaptchav2Plugin.getGreCaptchaInfo();

        expect(invisibleInfo).toEqual({
            version: 'GoogleReCaptchaV2',
            invisible: true,
        });
    });
});
