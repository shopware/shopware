import GoogleReCaptchaV3Plugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-v3.plugin';
import FormHandler from 'src/plugin/forms/form-handler.plugin';
import FormValidation from 'src/helper/form-validation.helper';

describe('GoogleReCaptchaV3Plugin tests', () => {
    let googleReCaptchaV3Plugin = undefined;
    let mockElement;
    let inputField;
    let originalPluginManager;

    beforeEach(() => {
        window.grecaptcha = {
            ready: (cb) => cb(),
            execute: jest.fn(() => Promise.resolve('successToken')),
        };

        mockElement = document.createElement('form');
        inputField = document.createElement('input');
        inputField.type = 'hidden';
        inputField.className = 'grecaptcha_v3-input';
        inputField.name = '_grecaptcha_v3';
        inputField.setAttribute('data-validation', 'grecaptcha,required');
        inputField.setAttribute('data-validate-hidden', 'true');

        mockElement.appendChild(inputField);

        const submitButton = document.createElement('button');
        submitButton.type = 'submit';
        mockElement.appendChild(submitButton);

        mockElement.submit = jest.fn();
        mockElement.checkValidity = jest.fn(() => true);

        document.body.appendChild(mockElement);

        originalPluginManager = window.PluginManager;
        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn(() => new Map()),
            getPlugin: jest.fn(() => new Map([['instances', []]])),
        };

        googleReCaptchaV3Plugin = new GoogleReCaptchaV3Plugin(mockElement, {
            siteKey: 'test-site-key',
            grecaptchaInputSelector: '.grecaptcha_v3-input',
        });
    });

    afterEach(() => {
        window.PluginManager = originalPluginManager;
        googleReCaptchaV3Plugin = undefined;

        if (mockElement && mockElement.parentElement) {
            mockElement.parentElement.removeChild(mockElement);
        }

        window.grecaptcha = undefined;
        window.formValidation = undefined;
        window.validationMessages = undefined;
        window.useDefaultCookieConsent = undefined;
    });

    test('GoogleReCaptchaV3Plugin exists', () => {
        expect(typeof googleReCaptchaV3Plugin).toBe('object');
    });

    test('grecaptcha execute on form submit', (done) => {
        googleReCaptchaV3Plugin._submitInvisibleForm = jest.fn();
        googleReCaptchaV3Plugin.grecaptcha.execute = jest.fn(() => Promise.resolve('successToken'));
        googleReCaptchaV3Plugin.grecaptcha.ready = googleReCaptchaV3Plugin._onGreCaptchaReady.bind(googleReCaptchaV3Plugin);

        googleReCaptchaV3Plugin.grecaptcha.value = null;
        googleReCaptchaV3Plugin._formSubmitting = true;
        googleReCaptchaV3Plugin.onFormSubmit();
        expect(googleReCaptchaV3Plugin.grecaptcha.execute).toHaveBeenCalled();

        expect(googleReCaptchaV3Plugin.grecaptchaInput.value).toEqual(GoogleReCaptchaV3Plugin.RECAPTCHA_PENDING_VALUE);
        expect(window.grecaptcha.execute).toHaveBeenCalledWith('test-site-key', { action: 'submit' });

        process.nextTick(() => {
            expect(googleReCaptchaV3Plugin.grecaptchaInput.value).toEqual('successToken');
            expect(googleReCaptchaV3Plugin._submitInvisibleForm).toHaveBeenCalled();
            done();
        });
    });

    test('form handler disables the submit button while reCAPTCHA v3 resolves', () => {
        window.validationMessages = {
            required: 'Input should not be empty.',
            email: 'Invalid email address.',
            confirmation: 'Confirmation field does not match.',
            minLength: 'Input is too short.',
            grecaptcha: 'reCAPTCHA cookies are required.',
        };
        window.useDefaultCookieConsent = false;
        window.formValidation = new FormValidation();
        window.grecaptcha.execute = jest.fn(() => new Promise(() => {}));

        new FormHandler(mockElement);

        const submitEvent = new Event('submit', { cancelable: true });
        const submitButton = mockElement.querySelector('button[type=submit]');

        mockElement.dispatchEvent(submitEvent);

        expect(submitEvent.defaultPrevented).toBe(true);
        expect(inputField.value).toEqual(GoogleReCaptchaV3Plugin.RECAPTCHA_PENDING_VALUE);
        expect(submitButton.disabled).toBe(true);
        expect(submitButton.querySelector('.loader')).not.toBeNull();
    });
});
