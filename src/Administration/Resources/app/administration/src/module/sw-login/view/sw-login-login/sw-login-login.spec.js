/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import useSystem from '../../../../app/composables/use-system';

const originalNavigatorLanguage = navigator.language;
const originalNavigatorLanguages = navigator.languages;

async function createWrapper(
    loginSuccessfull,
    useDefault = true,
    ssoUrl = 'https://sso.test',
    loginError = null,
    configFails = false,
) {
    const wrapper = mount(await wrapTestComponent('sw-login-login', { sync: true }), {
        global: {
            mocks: {
                $t: (...args) => JSON.stringify([...args]),
            },
            provide: {
                loginService: {
                    loginByUsername: () => {
                        if (loginSuccessfull) {
                            return Promise.resolve();
                        }

                        return new Promise((resolve, reject) => {
                            const response = loginError ?? {
                                config: {
                                    url: 'test.test.de',
                                },
                                response: {
                                    data: {
                                        errors: {
                                            status: 429,
                                            meta: {
                                                parameters: {
                                                    seconds: 1,
                                                },
                                            },
                                        },
                                    },
                                },
                            };

                            reject(response);
                        });
                    },
                    setRememberMe: (active = true) => {
                        if (!active) {
                            localStorage.removeItem('rememberMe');
                            return;
                        }

                        const duration = new Date();
                        duration.setDate(duration.getDate() + 14);

                        localStorage.setItem('rememberMe', `${+duration}`);
                    },
                    getLoginTemplateConfig: () => {
                        if (configFails) {
                            return Promise.reject(new Error('Failed to load login config'));
                        }

                        return Promise.resolve({ useDefault: useDefault, ssoProviders: [], url: ssoUrl });
                    },
                },
                userService: {},
                licenseViolationService: {},
            },
            stubs: {
                'router-view': true,
                'sw-loader': true,
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'router-link': true,
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
    });

    await flushPromises();

    if (!useDefault) {
        return { wrapper };
    }

    const passwordInput = wrapper.findByLabel('["sw-login.index.labelPassword"]');
    const usernameInput = wrapper.get('#sw-field--username');
    const rememberMeCheckbox = wrapper.find('.mt-field--checkbox__container input');

    return { wrapper, passwordInput, usernameInput, rememberMeCheckbox };
}

describe('module/sw-login/view/sw-login-login/sw-login-login.spec.js', () => {
    beforeEach(() => {
        Shopware.Application.getContainer('factory').locale.setSystemFallbackLocale(null);

        localStorage.removeItem('sw-admin-locale');

        Object.defineProperty(window.navigator, 'language', {
            value: originalNavigatorLanguage,
            configurable: true,
        });
        Object.defineProperty(window.navigator, 'languages', {
            value: originalNavigatorLanguages,
            configurable: true,
        });

        useSystem().locales.value = [];
        useSystem().registerAdminLocale('en-GB');
    });

    it('should show a warning if the login is rate limited', async () => {
        const { wrapper, usernameInput, passwordInput } = await createWrapper(false);
        jest.useFakeTimers();
        jest.spyOn(global, 'setTimeout');

        await usernameInput.setValue('Username');
        await passwordInput.setValue('Password');

        expect(wrapper.find('.sw-alert').exists()).toBe(false);

        await wrapper.get('.sw-login-login').trigger('submit');

        await flushPromises();

        // first call is emitting the `login-error`, the second is the timeout that clears the warning
        expect(setTimeout).toHaveBeenCalledTimes(2);
        expect(setTimeout).toHaveBeenLastCalledWith(expect.any(Function), 1000);

        expect(wrapper.get('[role="banner"]').text()).toBe('["sw-login.index.messageAuthThrottled",{"seconds":1},0]');

        // advance the timer to make the warning disappear
        jest.advanceTimersByTime(1001);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-alert').exists()).toBe(false);
    });

    it('should show an inline error banner for invalid credentials', async () => {
        const invalidCredentialsError = {
            config: {
                url: 'test.test.de',
            },
            response: {
                data: {
                    errors: {
                        status: 401,
                        code: '6',
                    },
                },
            },
        };

        const { wrapper, usernameInput, passwordInput } = await createWrapper(
            false,
            true,
            'https://sso.test',
            invalidCredentialsError,
        );

        await usernameInput.setValue('Username');
        await passwordInput.setValue('Password');

        expect(wrapper.find('.sw-login__error-banner').exists()).toBe(false);

        await wrapper.get('.sw-login-login').trigger('submit');

        await flushPromises();

        expect(wrapper.get('.sw-login__error-banner').text()).toBe('["sw-login.index.messageInvalidCredentials"]');
    });

    it('should show a generic error banner for malformed login error responses', async () => {
        const malformedError = {
            config: {
                url: 'test.test.de',
            },
            response: {},
        };

        const { wrapper, usernameInput, passwordInput } = await createWrapper(
            false,
            true,
            'https://sso.test',
            malformedError,
        );

        await usernameInput.setValue('Username');
        await passwordInput.setValue('Password');

        await wrapper.get('.sw-login-login').trigger('submit');

        await flushPromises();

        expect(wrapper.get('.sw-login__error-banner').text()).toBe(
            '["sw-login.index.messageGeneralRequestError",{"url":"test.test.de"}]',
        );
        expect(wrapper.emitted('is-not-loading')).toBeTruthy();
    });

    it('should show a generic error banner without a URL when the malformed response has none', async () => {
        const malformedError = {
            response: {},
        };

        const { wrapper, usernameInput, passwordInput } = await createWrapper(
            false,
            true,
            'https://sso.test',
            malformedError,
        );

        await usernameInput.setValue('Username');
        await passwordInput.setValue('Password');

        await wrapper.get('.sw-login-login').trigger('submit');

        await flushPromises();

        expect(wrapper.get('.sw-login__error-banner').text()).toBe('["sw-login.index.messageGeneralError"]');
    });

    it('should emit the loaded login config so the page does not need to fetch it again', async () => {
        const { wrapper } = await createWrapper(true);

        expect(wrapper.emitted('config-loaded')).toEqual([
            [
                { useDefault: true, ssoProviders: [], url: 'https://sso.test' },
            ],
        ]);
    });

    it('should fall back to the password login when the login config request fails', async () => {
        const { wrapper, usernameInput, passwordInput } = await createWrapper(true, true, 'https://sso.test', null, true);

        expect(wrapper.find('.sw-login__view-loader').exists()).toBe(false);
        expect(wrapper.find('.sw-login__sso').exists()).toBe(false);
        expect(usernameInput.exists()).toBe(true);
        expect(passwordInput.exists()).toBe(true);
    });

    it('should handle login', async () => {
        const { wrapper, usernameInput, passwordInput, rememberMeCheckbox } = await createWrapper(true);

        await usernameInput.setValue('admin');
        await passwordInput.setValue('admin');

        await rememberMeCheckbox.setChecked(true);

        const button = wrapper.find('button');
        await button.trigger('submit');

        await flushPromises();

        const expectedDuration = new Date();
        expectedDuration.setDate(expectedDuration.getDate() + 14);
        const rememberMeDuration = Number(localStorage.getItem('rememberMe'));
        expect(rememberMeDuration).toBeGreaterThan(1600000);
        expect(rememberMeDuration).toBeLessThanOrEqual(+expectedDuration);
    });

    it('should use the system fallback locale when browser and english fallbacks are unavailable', async () => {
        Object.defineProperty(window.navigator, 'language', {
            value: 'es-ES',
            configurable: true,
        });
        Object.defineProperty(window.navigator, 'languages', {
            value: ['es-ES'],
            configurable: true,
        });

        useSystem().locales.value = [];
        useSystem().registerAdminLocale('de-DE');
        Shopware.Application.getContainer('factory').locale.setSystemFallbackLocale('de-DE');

        const setAdminLocaleSpy = jest.spyOn(Shopware.Store.get('session'), 'setAdminLocale');

        await createWrapper(true);

        expect(setAdminLocaleSpy).toHaveBeenCalledWith('de-DE');
    });

    it('should redirect for SSO login', async () => {
        const navigateToSpy = jest.fn();
        const component = await wrapTestComponent('sw-login-login', { sync: true });
        component.methods._navigateTo = navigateToSpy;

        mount(component, {
            global: {
                mocks: {
                    $t: (...args) => JSON.stringify([...args]),
                },
                provide: {
                    loginService: {
                        loginByUsername: () => Promise.resolve(),
                        setRememberMe: () => {},
                        getLoginTemplateConfig: () => {
                            return Promise.resolve({ useDefault: false, ssoProviders: [], url: 'https://sso.test' });
                        },
                    },
                    userService: {},
                    licenseViolationService: {},
                },
                stubs: {
                    'router-view': true,
                    'sw-loader': true,
                    'sw-text-field': true,
                    'sw-text-field-deprecated': true,
                    'sw-contextual-field': true,
                    'sw-block-field': true,
                    'router-link': true,
                    'sw-checkbox-field': true,
                    'sw-checkbox-field-deprecated': true,
                    'sw-base-field': true,
                    'sw-field-error': true,
                    'sw-field-copyable': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
            },
        });

        await flushPromises();

        expect(navigateToSpy).toHaveBeenCalledWith('https://sso.test');
    });
});
