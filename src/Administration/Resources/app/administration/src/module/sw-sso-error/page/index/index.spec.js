/**
 * @sw-package after-sales
 */

import './index';

import { mount } from '@vue/test-utils';
import { CookieStorage } from 'cookie-storage';

const { Component } = Shopware;

async function createWrapper(useDefaultLogin, setCookie = true) {
    const wrapper = mount(await wrapTestComponent('sw-sso-error-index', { sync: true }), {
        global: {
            provide: {
                loginService: {
                    getLoginTemplateConfig: () => {
                        return Promise.resolve({
                            useDefault: useDefaultLogin,
                            url: 'https://foo.bar.baz',
                        });
                    },

                    getStorage: () => {
                        const storage = new CookieStorage();
                        if (setCookie) {
                            storage.setItem('user', 'foo@bar.baz');
                        }

                        return storage;
                    },
                },
            },
        },
    });

    return wrapper;
}

describe('src/module/sw-sso-error/page/index', () => {
    afterEach(async () => {
        const storage = new CookieStorage();
        storage.clear(); // important because of side effects (otherwise cookie items are persisted between tests)
    });

    it('should be available', async () => {
        await createWrapper(false);
        await flushPromises();

        const components = Component.getComponentRegistry();
        expect(components.has('sw-sso-error-index')).toBeTruthy();
    });

    it('should load the shopware logo', async () => {
        const wrapper = await createWrapper(false);
        await flushPromises();

        const shopwareLogo = wrapper.get('img.sw-sso-error__logo');

        expect(shopwareLogo.attributes('src')).toBe('administration/administration/static/img/shopware_logo_blue.svg');
    });

    it('should have the right text modules', async () => {
        const wrapper = await createWrapper(false);
        await flushPromises();

        expect(wrapper.find('.sw-sso-error__title').text()).toBe('global.sw-sso-error.error-card.title');
        expect(wrapper.find('.sw-sso-error__description').text()).toBe('global.sw-sso-error.error-card.text');
        expect(wrapper.find('.mt-button--primary').text()).toBe('global.sw-sso-error.error-card.button');
        expect(wrapper.find('.sw-sso-error__logged-in-as-text').text()).toBe(
            'global.sw-sso-error.error-card.loggedInAsPrefix',
        );
        expect(wrapper.find('.sw-sso-error__logged-in-as-email').text()).toBe('foo@bar.baz');
    });

    it('should not render the email if none is available', async () => {
        const wrapper = await createWrapper(false, false);
        await flushPromises();

        expect(wrapper.find('.sw-sso-error__logged-in-as-email').exists()).toBe(false);
    });

    it('should render the login button as a native link that opens in the same tab', async () => {
        const wrapper = await createWrapper(false);
        await flushPromises();

        const loginButton = wrapper.get('a.mt-button--primary');

        expect(loginButton.attributes('href')).toBe('https://foo.bar.baz&usePromptLogin=1');
        expect(loginButton.attributes('target')).toBe('_self');
    });
});
