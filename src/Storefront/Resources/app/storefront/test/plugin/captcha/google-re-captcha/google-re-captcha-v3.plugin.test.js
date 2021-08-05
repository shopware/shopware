import GoogleReCaptchaV3Plugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-v3.plugin';

describe('GoogleReCaptchaV3Plugin tests', () => {
    let googleReCaptchaV3Plugin = undefined;

    beforeEach(() => {
        window.grecaptcha = {
            ready: () => {},
            execute: () => {}
        };

        const mockElement = document.createElement('form');
        const inputField = document.createElement('input');
        inputField.className = 'grecaptcha_v3-input';

        mockElement.appendChild(inputField);

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
            }
        };

        googleReCaptchaV3Plugin = new GoogleReCaptchaV3Plugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha_v3-input'
        });
    });

    afterEach(() => {
        googleReCaptchaV3Plugin = undefined;
    });

    test('GoogleReCaptchaV3Plugin exists', () => {
        expect(typeof googleReCaptchaV3Plugin).toBe('object');
    });

    test('grecaptcha execute on form submit', () => {
        googleReCaptchaV3Plugin._submitInvisibleForm = jest.fn();
        googleReCaptchaV3Plugin.grecaptcha.execute = jest.fn(() => Promise.resolve('successToken'));
        googleReCaptchaV3Plugin.grecaptcha.ready = googleReCaptchaV3Plugin._onGreCaptchaReady.bind(googleReCaptchaV3Plugin);

        googleReCaptchaV3Plugin.grecaptcha.value = null;
        googleReCaptchaV3Plugin._formSubmitting = true;
        googleReCaptchaV3Plugin.onFormSubmit();
        expect(googleReCaptchaV3Plugin.grecaptcha.execute).toHaveBeenCalled();

        googleReCaptchaV3Plugin.grecaptcha.execute().then(() => {
            expect(googleReCaptchaV3Plugin._formSubmitting).toEqual(false);
            expect(googleReCaptchaV3Plugin.grecaptchaInput.value).toEqual('successToken');
            expect(googleReCaptchaV3Plugin._submitInvisibleForm).toHaveBeenCalled();
        })
    });
});


