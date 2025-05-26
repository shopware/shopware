import GoogleReCaptchaV2Plugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-v2.plugin';

describe('GoogleReCaptchaV2Plugin tests', () => {
    let googleReCaptchav2Plugin = undefined;
    let mockElement;
    let inputField;
    let captchaContainer;
    let mockIframe;

    beforeEach(() => {
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
        window.grecaptcha = undefined;
        mockIframe = null;
    });

    test('GoogleReCaptchaV2Plugin exists and iframe initialized', () => {
        expect(typeof googleReCaptchav2Plugin).toBe('object');
        expect(googleReCaptchav2Plugin.grecaptchaInput).toBeDefined();
        expect(googleReCaptchav2Plugin.grecaptcha).toBeDefined();
        expect(googleReCaptchav2Plugin.grecaptchaContainerIframe).toBe(mockIframe);
    });

    test('grecaptcha render is called on initialize', () => {
        expect(window.grecaptcha.render).toHaveBeenCalledWith(
            googleReCaptchav2Plugin.grecaptchaContainer,
            expect.objectContaining({
                sitekey: 'test-site-key-v2',
                size: 'normal',
            })
        );
        expect(googleReCaptchav2Plugin.grecaptchaWidgetId).toEqual('mockWidgetId');
        const iframe = document.createElement('iframe');
        googleReCaptchav2Plugin.grecaptchaContainer.appendChild(iframe);
        googleReCaptchav2Plugin._onGreCaptchaReady();

        expect(googleReCaptchav2Plugin.grecaptchaContainerIframe.tagName).toEqual('IFRAME');
    });

    test('grecaptcha execute is called on onFormSubmit if form is invisible', () => {
        googleReCaptchav2Plugin.options.invisible = true;
        googleReCaptchav2Plugin.grecaptchaWidgetId = 'testWidgetId';
        window.grecaptcha.execute = jest.fn(() => Promise.resolve('tokenInvisibleExecute'));
        googleReCaptchav2Plugin._submitInvisibleForm = jest.fn();

        googleReCaptchav2Plugin.onFormSubmit();

        expect(window.grecaptcha.execute).toHaveBeenCalledWith('testWidgetId');

        googleReCaptchav2Plugin.options.invisible = false;
        window.grecaptcha.execute.mockClear();
        googleReCaptchav2Plugin.grecaptchaInput.value = 'some-valid-token';

        googleReCaptchav2Plugin.onFormSubmit();

        expect(window.grecaptcha.execute).not.toHaveBeenCalled();
    });

    test('_submitInvisibleForm is called on captcha token response if form is invisible', () => {
        googleReCaptchav2Plugin.options.invisible = false;
        googleReCaptchav2Plugin._submitInvisibleForm = jest.fn();
        googleReCaptchav2Plugin.grecaptchaContainerIframe = mockIframe;

        googleReCaptchav2Plugin._onCaptchaTokenResponse('tokenVisible');

        expect(googleReCaptchav2Plugin.grecaptchaInput.value).toEqual('tokenVisible');
        expect(googleReCaptchav2Plugin._submitInvisibleForm).not.toHaveBeenCalled();

        googleReCaptchav2Plugin.options.invisible = true;
        googleReCaptchav2Plugin._onCaptchaTokenResponse('tokenInvisible');

        expect(googleReCaptchav2Plugin.grecaptchaInput.value).toEqual('tokenInvisible');
        expect(googleReCaptchav2Plugin._submitInvisibleForm).toHaveBeenCalled();
    });

    test('iframe get highlighted if grecaptcha input value is not set', () => {
        googleReCaptchav2Plugin.options.invisible = false;
        expect(googleReCaptchav2Plugin.grecaptchaContainerIframe).toBe(mockIframe);

        googleReCaptchav2Plugin.grecaptchaInput.value = 'token';
        googleReCaptchav2Plugin.onFormSubmit();
        expect(googleReCaptchav2Plugin.grecaptchaContainerIframe.classList.contains(googleReCaptchav2Plugin.options.grecaptchaIframeHasErrorClassSelector)).toEqual(false);

        googleReCaptchav2Plugin.grecaptchaInput.value = '';
        googleReCaptchav2Plugin.onFormSubmit();
        expect(googleReCaptchav2Plugin.grecaptchaContainerIframe.classList.contains(googleReCaptchav2Plugin.options.grecaptchaIframeHasErrorClassSelector)).toEqual(true);
    });

    test('grecaptcha input value is set on captcha token response', () => {
        googleReCaptchav2Plugin.options.invisible = false;
        googleReCaptchav2Plugin._submitInvisibleForm = jest.fn();
        googleReCaptchav2Plugin.grecaptchaContainerIframe = mockIframe;

        googleReCaptchav2Plugin._onCaptchaTokenResponse('testResponseToken');

        expect(googleReCaptchav2Plugin.grecaptchaInput.value).toEqual('testResponseToken');
        expect(mockIframe.classList.contains(googleReCaptchav2Plugin.options.grecaptchaIframeHasErrorClassSelector)).toBe(false);
    });
});


