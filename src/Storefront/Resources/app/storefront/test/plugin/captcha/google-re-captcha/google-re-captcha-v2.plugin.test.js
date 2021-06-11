import GoogleReCaptchaV2Plugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-v2.plugin';

describe('GoogleReCaptchaV2Plugin tests', () => {
    let googleReCaptchav2Plugin = undefined;

    beforeEach(() => {
        window.grecaptcha = {
            ready: () => {},
            execute: () => {}
        };

        window.router = {};

        const mockElement = document.createElement('form');
        const inputField = document.createElement('input');
        const iframe = document.createElement('iframe');
        inputField.className = 'grecaptcha-input';
        mockElement.appendChild(inputField);
        mockElement.appendChild(iframe);

        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPluginInstanceFromElement: () => {
                return new Map();
            },
            getPluginInstances: () => {
                return new Map();
            },
            getPlugin: () => {
                return {
                    get: () => []
                };
            },
        };

        googleReCaptchav2Plugin = new GoogleReCaptchaV2Plugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input'
        });
    });

    afterEach(() => {
        googleReCaptchav2Plugin = undefined;
    });

    test('GoogleReCaptchaV2Plugin exists', () => {
        expect(typeof googleReCaptchav2Plugin).toBe('object');
    });

    test('grecaptcha render is called on initialize', () => {
        googleReCaptchav2Plugin.grecaptcha.render = jest.fn(() => { return 'widgetId'; });
        googleReCaptchav2Plugin.grecaptcha.ready = googleReCaptchav2Plugin._onGreCaptchaReady.bind(googleReCaptchav2Plugin);

        googleReCaptchav2Plugin.init();

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


