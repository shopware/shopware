import GoogleReCaptchaV3Plugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-v3.plugin';

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
        inputField.className = 'grecaptcha_v3-input';

        mockElement.appendChild(inputField);
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

        expect(window.grecaptcha.execute).toHaveBeenCalledWith('test-site-key', { action: 'submit' });

        process.nextTick(() => {
            expect(googleReCaptchaV3Plugin.grecaptchaInput.value).toEqual('successTokenForThisTest');
            expect(googleReCaptchaV3Plugin._submitInvisibleForm).toHaveBeenCalled();
            expect(googleReCaptchaV3Plugin._formSubmitting).toBe(false);
            done();
        });
    });
});
