import BasicCaptchaPlugin from 'src/plugin/captcha/basic-captcha.plugin';
import FormValidation from 'src/helper/form-validation.helper';
import Feature from 'src/helper/feature.helper';

describe('BasicCaptchaPlugin tests', () => {
    let basicCaptchaPlugin;
    let captchaElement;

    beforeEach(async () => {
        document.body.innerHTML = `
            <form>
                <div id="basic-captcha">
                    <div id="basic-captcha-content-image"></div>
                    <a id="basic-captcha-content-refresh-icon">Refresh</a>
                    <label for="basic-captcha-input">Captcha</label>
                    <input id="basic-captcha-input" aria-describedby="basic-captcha-input-feedback">
                    <div id="basic-captcha-input-feedback"></div>
                </div>
            </form>
        `;

        window.Feature = Feature;
        window.Feature.init({ 'ACCESSIBILITY_TWEAKS': true });

        window.validationMessages = {
            required: 'Input should not be empty.',
            email: 'Invalid email address.',
            confirmation: 'Confirmation field does not match.',
            minLength: 'Input is too short.',
        };

        window.formValidation = new FormValidation();

        // Create spy elements
        window.PluginManager.initializePlugins = jest.fn();

        captchaElement = document.getElementById('basic-captcha');
    });

    test('Captcha should be loaded on initialization', () => {
        const loadBasicCaptchaSpy  = jest.spyOn(BasicCaptchaPlugin.prototype, '_onLoadBasicCaptcha');

        basicCaptchaPlugin = new BasicCaptchaPlugin(captchaElement);

        expect(loadBasicCaptchaSpy).toHaveBeenCalled();
    });

    test('Captcha should be updated if reload button is clicked', () => {
        const captchaReloadSpy = jest.spyOn(BasicCaptchaPlugin.prototype, '_onLoadBasicCaptcha');

        basicCaptchaPlugin = new BasicCaptchaPlugin(captchaElement);

        const httpClientSpy = jest.spyOn(basicCaptchaPlugin._httpClient, 'get');
        const reloadButton = basicCaptchaPlugin.el.querySelector(basicCaptchaPlugin.options.captchaRefreshIconId);

        reloadButton.click();

        // One call on initialization and one on reload button click.
        expect(captchaReloadSpy).toHaveBeenCalledTimes(2);
        expect(httpClientSpy).toHaveBeenCalled();
    });

    test('Form submit should be prevented if captcha is invalid', async () => {
        const validationSpy = jest.spyOn(BasicCaptchaPlugin.prototype, 'validateCaptcha');

        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve({
                    type: 'danger',
                    error: 'invalid_captcha',
                }),
            })
        );

        basicCaptchaPlugin = new BasicCaptchaPlugin(captchaElement);
        basicCaptchaPlugin._form.submit = jest.fn();

        const captchaInput = basicCaptchaPlugin._form.querySelector(basicCaptchaPlugin.options.basicCaptchaInputId);

        await basicCaptchaPlugin.validateCaptcha(new Event('submit'));

        await expect(validationSpy).toHaveBeenCalledTimes(1);
        await expect(basicCaptchaPlugin._form.submit).toHaveBeenCalledTimes(0);

        expect(captchaInput.classList).toContain(window.formValidation.config.invalidClass);
    });

    test('Form should be submitted if captcha is valid', async () => {
        const validationSpy = jest.spyOn(BasicCaptchaPlugin.prototype, 'validateCaptcha');

        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve({
                    session: {},
                }),
            })
        );

        basicCaptchaPlugin = new BasicCaptchaPlugin(captchaElement);
        basicCaptchaPlugin._form.submit = jest.fn();

        const captchaInput = basicCaptchaPlugin._form.querySelector(basicCaptchaPlugin.options.basicCaptchaInputId);

        await basicCaptchaPlugin.validateCaptcha(new Event('submit'));

        await expect(validationSpy).toHaveBeenCalledTimes(1);
        await expect(basicCaptchaPlugin._form.submit).toHaveBeenCalledTimes(1);

        expect(captchaInput.classList).not.toContain(window.formValidation.config.invalidClass);
    });
});
