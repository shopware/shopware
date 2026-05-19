import GoogleReCaptchaV3Plugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-v3.plugin';
import FormHandler from 'src/plugin/forms/form-handler.plugin';
import FormValidation from 'src/helper/form-validation.helper';

describe('GoogleReCaptchaV3Plugin tests', () => {
    let googleReCaptchaV3Plugin = undefined;
    let mockElement;
    let inputField;
    let mockRecaptchaScriptElement; // Added for the mock script

    beforeEach(() => {
        window.grecaptcha = {
            ready: jest.fn(callback => callback()),
            execute: jest.fn(() => Promise.resolve('mockExecuteToken')),
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

        document.body.appendChild(mockElement);

        // Add the mock recaptcha script element
        mockRecaptchaScriptElement = document.createElement('script');
        mockRecaptchaScriptElement.id = 'recaptcha-script';
        document.body.appendChild(mockRecaptchaScriptElement);

        googleReCaptchaV3Plugin = new GoogleReCaptchaV3Plugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha_v3-input',
            siteKey: 'test-site-key',
        });
    });

    afterEach(() => {
        googleReCaptchaV3Plugin = undefined;
        if (mockElement?.parentElement) {
            mockElement.parentElement.removeChild(mockElement);
        }
        // Remove the mock recaptcha script element
        if (mockRecaptchaScriptElement?.parentElement) {
            mockRecaptchaScriptElement.parentElement.removeChild(mockRecaptchaScriptElement);
        }
        window.grecaptcha = undefined;
        window.formValidation = undefined;
        window.validationMessages = undefined;
        window.useDefaultCookieConsent = undefined;
    });

    test('GoogleReCaptchaV3Plugin exists', () => {
        expect(typeof googleReCaptchaV3Plugin).toBe('object');
        expect(googleReCaptchaV3Plugin.grecaptchaInput).toBeDefined();
        expect(googleReCaptchaV3Plugin.grecaptcha).toBeDefined();
        expect(googleReCaptchaV3Plugin.grecaptcha).toBe(window.grecaptcha);
    });

    test('grecaptcha execute on form submit', (done) => {
        googleReCaptchaV3Plugin._submitInvisibleForm = jest.fn();
        window.grecaptcha.execute = jest.fn(() => Promise.resolve('successTokenForThisTest'));

        googleReCaptchaV3Plugin.onFormSubmit();

        expect(googleReCaptchaV3Plugin.grecaptchaInput.value).toEqual(GoogleReCaptchaV3Plugin.RECAPTCHA_PENDING_VALUE);
        expect(window.grecaptcha.execute).toHaveBeenCalledWith('test-site-key', { action: 'submit' });

        process.nextTick(() => {
            expect(googleReCaptchaV3Plugin.grecaptchaInput.value).toEqual('successTokenForThisTest');
            expect(googleReCaptchaV3Plugin._submitInvisibleForm).toHaveBeenCalled();
            expect(googleReCaptchaV3Plugin._formSubmitting).toBe(false);
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
