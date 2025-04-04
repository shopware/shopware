/**
 * @sw-package after-sales
 */

import './index';

import { mount } from '@vue/test-utils';
import { CookieStorage } from "cookie-storage";

const { Component } = Shopware;

async function createWrapper(useDefaultLogin) {
    const wrapper = mount(await wrapTestComponent('sw-sso-error-index', { sync: true }), {
        global: {
            provide: {
                loginService: {
                    getLoginTemplateConfig: () => {
                        return new Promise((resolve) =>  {
                            const response = {
                                useDefault: useDefaultLogin,
                                url: 'https://foo.bar.baz',
                            }

                            resolve(response);
                        });
                    },

                    getStorage: () =>  {
                        const storage = new CookieStorage();
                        storage.setItem('user', 'foo@bar.baz');

                        return storage;
                    }
                }
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('src/module/sw-sso-error/page/index', () => {
    it('should be available', () => {
        const components = Component.getComponentRegistry();
        expect(components.has('sw-sso-error-index')).toBe(true);
    });

    it('should load the shopware logo', async () => {
        const wrapper = await createWrapper(false);
        await flushPromises();

        const shopwareLogo = wrapper.get('.sw-sso-error__image-container > img');

        expect(shopwareLogo.attributes('src')).toBe('administration/administration/static/img/shopware_logo_blue.svg');
    });

    it('should have the right text modules', async () => {
        const wrapper = await createWrapper(false);
        await flushPromises();

        expect(wrapper.find('.sw-sso-error__title').text()).toBe('sw-sso-error.error-card.title');
        expect(wrapper.find('.sw-sso-error__description').text()).toBe('sw-sso-error.error-card.text');
        expect(wrapper.find('.sw-button.sw-button--primary').text()).toBe('sw-sso-error.error-card.button');
        expect(wrapper.find('.sw-sso-error-card__small-text').text()).toBe('sw-sso-error.error-card.loggedInAsPrefix');
        expect(wrapper.find('.sw-sso-error-card__small-text-email').text()).toBe('foo@bar.baz');
    });
});
