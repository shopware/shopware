import GoogleReCaptchaV2Plugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-v2.plugin';

describe('GoogleReCaptchaV2Plugin tests', () => {
    let googleReCaptchav2Plugin = undefined;
    let mockElement;
    let originalPluginManager;

    beforeEach(() => {
        window.grecaptcha = {
            ready: (cb) => cb(),
            execute: jest.fn(() => Promise.resolve('token')),
            render: jest.fn(() => 'widgetId'),
        };

        mockElement = document.createElement('form');
        const inputField = document.createElement('input');
        const iframe = document.createElement('iframe');
        const container = document.createElement('div');
        container.className = 'grecaptcha-v2-container';
        inputField.className = 'grecaptcha-v2-input';
        mockElement.appendChild(inputField);
        mockElement.appendChild(iframe);
        mockElement.appendChild(container);

        mockElement.submit = jest.fn();
        mockElement.checkValidity = jest.fn(() => true);

        document.body.appendChild(mockElement);

        originalPluginManager = window.PluginManager;
        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn(() => new Map()),
            getPlugin: jest.fn(() => new Map([['instances', []]])),
        };

        googleReCaptchav2Plugin = new GoogleReCaptchaV2Plugin(mockElement);
    });

    afterEach(() => {
        window.PluginManager = originalPluginManager;
        googleReCaptchav2Plugin = undefined;

        if (mockElement && mockElement.parentElement) {
            mockElement.parentElement.removeChild(mockElement);
        }
    });

    test('GoogleReCaptchaV2Plugin exists', () => {
        expect(typeof googleReCaptchav2Plugin).toBe('object');
    });

    test('grecaptcha render is called on initialize', () => {
        expect(googleReCaptchav2Plugin.grecaptcha.render).toHaveBeenCalled();
        expect(googleReCaptchav2Plugin.grecaptchaWidgetId).toEqual('widgetId');
        expect(googleReCaptchav2Plugin.grecaptchaContainerIframe.tagName).toEqual('IFRAME');
    });

    test('grecaptcha execute is called on onFormSubmit if form is invisible', () => {
        googleReCaptchav2Plugin.options.invisible = true;
        googleReCaptchav2Plugin.grecaptchaWidgetId = true;
        googleReCaptchav2Plugin.grecaptcha.execute = jest.fn(() => Promise.resolve('token'));

        googleReCaptchav2Plugin.onFormSubmit();

        expect(googleReCaptchav2Plugin.grecaptcha.execute).toHaveBeenCalled();

        googleReCaptchav2Plugin.grecaptchaContainerIframe = document.createElement('iframe');
        googleReCaptchav2Plugin.options.invisible = false;
        googleReCaptchav2Plugin.grecaptcha.execute = jest.fn(() => Promise.resolve('token'));

        googleReCaptchav2Plugin.onFormSubmit();
        expect(googleReCaptchav2Plugin.grecaptcha.execute).not.toHaveBeenCalled();
    });

    test('_submitInvisibleForm is called on captcha token response if form is invisible', () => {
        googleReCaptchav2Plugin.options.invisible = false;
        googleReCaptchav2Plugin._submitInvisibleForm = jest.fn();
        googleReCaptchav2Plugin.grecaptchaContainerIframe = document.createElement('iframe');

        googleReCaptchav2Plugin._onCaptchaTokenResponse('token');

        expect(googleReCaptchav2Plugin._submitInvisibleForm).not.toHaveBeenCalled();

        googleReCaptchav2Plugin.options.invisible = true;
        googleReCaptchav2Plugin._onCaptchaTokenResponse('token');

        expect(googleReCaptchav2Plugin.grecaptchaInput.value).toEqual('token');

        expect(googleReCaptchav2Plugin._submitInvisibleForm).toHaveBeenCalled();
    });

    test('iframe get highlighted if grecaptcha input value is not set', () => {
        googleReCaptchav2Plugin.grecaptchaContainerIframe = document.createElement('iframe');
        googleReCaptchav2Plugin.options.invisible = false;
        googleReCaptchav2Plugin.grecaptcha.execute = jest.fn(() => Promise.resolve('token'));

        googleReCaptchav2Plugin.grecaptchaInput.value = 'token';

        googleReCaptchav2Plugin.onFormSubmit();
        expect(googleReCaptchav2Plugin.grecaptchaContainerIframe.classList.contains(googleReCaptchav2Plugin.options.grecaptchaIframeHasErrorClassSelector)).toEqual(false);

        googleReCaptchav2Plugin.grecaptchaInput.value = null;

        googleReCaptchav2Plugin.onFormSubmit();

        expect(googleReCaptchav2Plugin.grecaptchaContainerIframe.classList.contains(googleReCaptchav2Plugin.options.grecaptchaIframeHasErrorClassSelector)).toEqual(true);
    });

    test('grecaptcha input value is set on captcha token response', () => {
        googleReCaptchav2Plugin.options.invisible = false;
        googleReCaptchav2Plugin._submitInvisibleForm = jest.fn();
        googleReCaptchav2Plugin.grecaptchaContainerIframe = document.createElement('iframe');

        googleReCaptchav2Plugin._onCaptchaTokenResponse('token');

        expect(googleReCaptchav2Plugin.grecaptchaInput.value).toEqual('token');
    });
});
