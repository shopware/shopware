/**
 * @jest-environment jsdom
 */

import BasicCaptchaPlugin from 'src/plugin/captcha/basic-captcha.plugin';

describe('BasicCaptchaPlugin tests', () => {
    let basicCaptchaPlugin = undefined;
    let spyInit = jest.fn();
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        const mockElement = document.createElement('div');
        mockElement.innerHTML += '<div>' +
            '<div id="basic-captcha-content-image"></div>' +
            '<a id="basic-captcha-content-refresh-icon">Icon</a>' +
            '</div>';

        window.csrf = {
            enabled: false
        };

        window.router = [];

        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPlugin: () => {
                return {
                    get: () => []
                };
            },
            initializePlugins: undefined
        };

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
});
