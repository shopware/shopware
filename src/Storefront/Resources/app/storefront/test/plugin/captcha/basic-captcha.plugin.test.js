import BasicCaptchaPlugin from 'src/plugin/captcha/basic-captcha.plugin';

describe('BasicCaptchaPlugin tests', () => {
    let basicCaptchaPlugin = undefined;
    let spyInit = jest.fn();
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        const mockElement = document.createElement('form');
        const mockDiv = document.createElement('div');
        mockDiv.innerHTML += '<div>' +
            '<div id="basic-captcha-content-image"></div>' +
            '<a id="basic-captcha-content-refresh-icon">Icon</a>' +
            '<input id="basic-captcha-input">' +
            '<input id="-precheck">' +
            '</div>';
        mockElement.appendChild(mockDiv);

        // mock basic captcha plugins
        basicCaptchaPlugin = new BasicCaptchaPlugin(mockElement);

        // create spy elements
        window.PluginManager.initializePlugins = spyInitializePlugins;
    });

    afterEach(() => {
        basicCaptchaPlugin = undefined;
        spyInit.mockClear();
        spyInitializePlugins.mockClear();
        window.PluginManager.initializePlugins = undefined;
    });

    test('basicCaptchaPlugin exists', () => {
        expect(typeof basicCaptchaPlugin).toBe('object');
    });

    test('_onLoadBasicCaptcha should get called', () => {
        const a  = jest.spyOn(basicCaptchaPlugin, '_onLoadBasicCaptcha');
        basicCaptchaPlugin.init();
        expect(a).toHaveBeenCalled();
    });

    test('_onLoadBasicCaptcha and _httpClient.get should get called when click', () => {
        const btn = basicCaptchaPlugin.el.querySelector(basicCaptchaPlugin.options.captchaRefreshIconId);

        const spyOnLoad = jest.spyOn(basicCaptchaPlugin, '_onLoadBasicCaptcha');
        const spyCallApi = jest.spyOn(basicCaptchaPlugin._httpClient, 'get');
        btn.click();
        basicCaptchaPlugin.init();

        expect(spyOnLoad).toHaveBeenCalled();
        expect(spyCallApi).toHaveBeenCalled();
    });

    test('onFormSubmit should get called', () => {
        basicCaptchaPlugin._form.submit = jest.fn();

        basicCaptchaPlugin._form.checkValidity = () => { return true };

        basicCaptchaPlugin.onFormSubmit('kyln');

        expect(basicCaptchaPlugin._form.submit).toHaveBeenCalled();
    });

    test('onFormSubmit should not get called when the form invalid', () => {
        basicCaptchaPlugin._form.submit = jest.fn();

        basicCaptchaPlugin._form.checkValidity = () => { return false };

        basicCaptchaPlugin.onFormSubmit('kyln');

        expect(basicCaptchaPlugin._form.submit).not.toHaveBeenCalled();
    });
});
