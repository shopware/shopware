/**
 * @jest-environment jsdom
 */
import GoogleReCaptchaBasePlugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-base.plugin';

describe('GoogleReCaptchaBasePlugin tests', () => {
    let googleReCaptchaBasePlugin = undefined;

    beforeEach(() => {
        window.grecaptcha = {
            ready: () => {},
            execute: () => {}
        };

        const mockElement = document.createElement('form');
        const inputField = document.createElement('input');
        inputField.className = 'grecaptcha-input';
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
            },
        };

        googleReCaptchaBasePlugin = new GoogleReCaptchaBasePlugin(mockElement, {
            grecaptchaInputSelector: '.grecaptcha-input'
        });
    });

    afterEach(() => {
        googleReCaptchaBasePlugin = undefined;
    });

    test('GoogleReCaptchaBasePlugin exists', () => {
        expect(typeof googleReCaptchaBasePlugin).toBe('object');
    });

    test('Throw error if input field for Google reCAPTCHA is missing', () => {
        const mockForm = document.createElement('form');

        expect(() => new GoogleReCaptchaBasePlugin(mockForm)).toThrow(Error('Input field for Google reCAPTCHA is missing!'));

        const inputField = document.createElement('input');
        inputField.className = 'grecaptcha-input';
        mockForm.appendChild(inputField);

        googleReCaptchaBasePlugin = new GoogleReCaptchaBasePlugin(mockForm, {
            grecaptchaInputSelector: '.grecaptcha-input'
        });

        expect(typeof googleReCaptchaBasePlugin).toBe('object');
    });

    test('onFormSubmit is called _onFormSubmitCallback', () => {
        googleReCaptchaBasePlugin.onFormSubmit = jest.fn();

        googleReCaptchaBasePlugin._formSubmitting = true;

        googleReCaptchaBasePlugin._onFormSubmitCallback();

        expect(googleReCaptchaBasePlugin.onFormSubmit).not.toHaveBeenCalled();
        expect(googleReCaptchaBasePlugin._formSubmitting).toEqual(true);

        googleReCaptchaBasePlugin._formSubmitting = false;

        googleReCaptchaBasePlugin._onFormSubmitCallback();
        expect(googleReCaptchaBasePlugin.onFormSubmit).toHaveBeenCalled();
    });

    test('form is not submitted is not validated', () => {
        googleReCaptchaBasePlugin._form.submit = jest.fn();
        googleReCaptchaBasePlugin._form.checkValidity = () => { return false };

        googleReCaptchaBasePlugin._submitInvisibleForm();

        expect(googleReCaptchaBasePlugin._form.submit).not.toHaveBeenCalled();

        googleReCaptchaBasePlugin._form.checkValidity = () => { return true };

        googleReCaptchaBasePlugin._submitInvisibleForm();

        expect(googleReCaptchaBasePlugin._form.submit).toHaveBeenCalled();
    });
});


