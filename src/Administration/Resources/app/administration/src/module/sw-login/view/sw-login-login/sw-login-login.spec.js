/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import useSystem from '../../../../app/composables/use-system';

const originalNavigatorLanguage = navigator.language;
const originalNavigatorLanguages = navigator.languages;

async function createWrapper(loginSuccessfull, { loginService = {}, mocks = {} } = {}) {
    const loginServiceMock = {
        loginByUsername: () => {
            if (loginSuccessfull) {
                return Promise.resolve();
            }

            return new Promise((resolve, reject) => {
                const response = {
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
        getAvailableAuthMethods: jest.fn(() => Promise.resolve([])),
        loginPrimary: jest.fn(() => Promise.resolve({ mfaRequired: false, methods: [], auth: {} })),
        loginWithPasskey: jest.fn(() => Promise.resolve({ mfaRequired: false, methods: [], auth: {} })),
        clearPendingMfa: jest.fn(),
        ...loginService,
    };

    const wrapper = mount(await wrapTestComponent('sw-login-login', { sync: true }), {
        global: {
            mocks: {
                $t: (...args) => JSON.stringify([...args]),
                ...mocks,
            },
            provide: {
                loginService: loginServiceMock,
                userService: {},
                licenseViolationService: {},
            },
            stubs: {
                'sw-login-mfa': true,
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

    const passwordInput = wrapper.findByLabel('["sw-login.index.labelPassword"]');
    const usernameInput = wrapper.get('#sw-field--username');
    const rememberMeCheckbox = wrapper.find('.mt-field--checkbox__container input');

    return { wrapper, passwordInput, usernameInput, rememberMeCheckbox, loginServiceMock };
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

    describe('ADMIN_AUTH feature flag', () => {
        const originalPublicKeyCredential = window.PublicKeyCredential;
        const originalCredentials = Object.getOwnPropertyDescriptor(window.navigator, 'credentials');

        function mockWebAuthnSupport() {
            Object.defineProperty(window, 'PublicKeyCredential', {
                value: function PublicKeyCredential() {},
                configurable: true,
                writable: true,
            });
            Object.defineProperty(window.navigator, 'credentials', {
                value: { get: jest.fn() },
                configurable: true,
            });
        }

        afterEach(() => {
            Object.defineProperty(window, 'PublicKeyCredential', {
                value: originalPublicKeyCredential,
                configurable: true,
                writable: true,
            });

            if (originalCredentials) {
                Object.defineProperty(window.navigator, 'credentials', originalCredentials);
            } else {
                Object.defineProperty(window.navigator, 'credentials', {
                    value: undefined,
                    configurable: true,
                });
            }
        });

        it('should not fetch the auth methods and use the legacy login when the flag is off', async () => {
            const loginByUsername = jest.fn(() => Promise.resolve());
            const { wrapper, usernameInput, passwordInput, loginServiceMock } = await createWrapper(true, {
                loginService: { loginByUsername },
            });

            expect(loginServiceMock.getAvailableAuthMethods).not.toHaveBeenCalled();

            await usernameInput.setValue('admin');
            await passwordInput.setValue('shopware');
            await wrapper.get('.sw-login-login').trigger('submit');
            await flushPromises();

            expect(loginByUsername).toHaveBeenCalledWith('admin', 'shopware');
            expect(loginServiceMock.loginPrimary).not.toHaveBeenCalled();
        });

        it('should fetch the auth methods when the flag is on', async () => {
            global.activeFeatureFlags = ['ADMIN_AUTH'];

            const { loginServiceMock } = await createWrapper(true);

            expect(loginServiceMock.getAvailableAuthMethods).toHaveBeenCalledTimes(1);
        });

        it('should render a provider button for oidc methods and navigate to its start url', async () => {
            global.activeFeatureFlags = ['ADMIN_AUTH'];

            const startUrl = 'https://shop.example/api/_action/admin-auth/oidc/okta/start';
            const { wrapper } = await createWrapper(true, {
                loginService: {
                    getAvailableAuthMethods: jest.fn(() =>
                        Promise.resolve([
                            { id: 'okta', type: 'oidc', label: 'Okta', startUrl },
                        ]),
                    ),
                },
            });

            const navigateSpy = jest.spyOn(wrapper.vm, '_navigateTo').mockImplementation(() => {});

            const providerButton = wrapper.get('.sw-login__sso-provider-action');
            expect(providerButton.text()).toBe('Okta');

            await providerButton.trigger('click');

            expect(navigateSpy).toHaveBeenCalledWith(startUrl);
        });

        it('should show the passkey button only when webauthn is offered and the browser supports it', async () => {
            global.activeFeatureFlags = ['ADMIN_AUTH'];
            mockWebAuthnSupport();

            const { wrapper, loginServiceMock } = await createWrapper(true, {
                loginService: {
                    getAvailableAuthMethods: jest.fn(() =>
                        Promise.resolve([
                            { id: 'password', type: 'password', label: null, startUrl: null },
                            { id: 'webauthn', type: 'webauthn', label: null, startUrl: null },
                        ]),
                    ),
                },
            });

            const passkeyButton = wrapper.get('.sw-login__passkey-action');

            await passkeyButton.trigger('click');
            await flushPromises();

            expect(loginServiceMock.loginWithPasskey).toHaveBeenCalledTimes(1);
        });

        it('should hide the passkey button when the browser does not support WebAuthn', async () => {
            global.activeFeatureFlags = ['ADMIN_AUTH'];

            Object.defineProperty(window, 'PublicKeyCredential', {
                value: undefined,
                configurable: true,
                writable: true,
            });

            const { wrapper } = await createWrapper(true, {
                loginService: {
                    getAvailableAuthMethods: jest.fn(() =>
                        Promise.resolve([
                            { id: 'webauthn', type: 'webauthn', label: null, startUrl: null },
                        ]),
                    ),
                },
            });

            expect(wrapper.find('.sw-login__passkey-action').exists()).toBe(false);
        });

        it('should log in via the admin_primary grant when the flag is on', async () => {
            global.activeFeatureFlags = ['ADMIN_AUTH'];

            const { wrapper, usernameInput, passwordInput, loginServiceMock } = await createWrapper(true);

            await usernameInput.setValue('admin');
            await passwordInput.setValue('shopware');
            await wrapper.get('.sw-login-login').trigger('submit');
            await flushPromises();

            expect(loginServiceMock.loginPrimary).toHaveBeenCalledWith('admin', 'shopware');
        });

        it('should switch to the MFA step and clear the password when a second factor is required', async () => {
            global.activeFeatureFlags = ['ADMIN_AUTH'];

            const { wrapper, usernameInput, passwordInput } = await createWrapper(true, {
                loginService: {
                    loginPrimary: jest.fn(() =>
                        Promise.resolve({
                            mfaRequired: true,
                            methods: ['totp'],
                            auth: null,
                        }),
                    ),
                },
            });

            await usernameInput.setValue('admin');
            await passwordInput.setValue('shopware');
            await wrapper.get('.sw-login-login').trigger('submit');
            await flushPromises();

            expect(wrapper.vm.mfaRequired).toBe(true);
            expect(wrapper.vm.mfaMethods).toEqual(['totp']);
            expect(wrapper.vm.password).toBe('');

            const mfaStub = wrapper.find('sw-login-mfa-stub');
            expect(mfaStub.exists()).toBe(true);
            expect(wrapper.find('#sw-field--username').exists()).toBe(false);
        });

        it('should clear the pending MFA state when the MFA step is cancelled', async () => {
            global.activeFeatureFlags = ['ADMIN_AUTH'];

            const { wrapper, usernameInput, passwordInput, loginServiceMock } = await createWrapper(true, {
                loginService: {
                    loginPrimary: jest.fn(() =>
                        Promise.resolve({
                            mfaRequired: true,
                            methods: ['totp'],
                            auth: null,
                        }),
                    ),
                },
            });

            await usernameInput.setValue('admin');
            await passwordInput.setValue('shopware');
            await wrapper.get('.sw-login-login').trigger('submit');
            await flushPromises();

            await wrapper.get('sw-login-mfa-stub').trigger('mfa-cancel');
            await flushPromises();

            expect(loginServiceMock.clearPendingMfa).toHaveBeenCalledTimes(1);
            expect(wrapper.vm.mfaRequired).toBe(false);
            expect(wrapper.find('#sw-field--username').exists()).toBe(true);
        });

        it('should still render the password form when the methods fetch fails', async () => {
            global.activeFeatureFlags = ['ADMIN_AUTH'];

            const { wrapper } = await createWrapper(true, {
                loginService: {
                    getAvailableAuthMethods: jest.fn(() => Promise.reject(new Error('offline'))),
                },
            });

            expect(wrapper.find('#sw-field--username').exists()).toBe(true);
            expect(wrapper.find('.sw-login__sso-provider-action').exists()).toBe(false);
            expect(wrapper.vm.authMethodsLoaded).toBe(true);
        });

        it('should show a notification for the ssoError query parameter', async () => {
            global.activeFeatureFlags = ['ADMIN_AUTH'];

            const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

            await createWrapper(true, {
                mocks: {
                    $route: { params: {}, query: { ssoError: 'mfa-required' } },
                },
            });

            expect(notificationSpy).toHaveBeenCalledWith(
                expect.objectContaining({
                    variant: 'error',
                    message: JSON.stringify(['sw-login.index.ssoErrorMfaRequired']),
                }),
            );

            notificationSpy.mockRestore();
        });

        it('should fall back to a generic message for unknown ssoError codes', async () => {
            global.activeFeatureFlags = ['ADMIN_AUTH'];

            const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

            await createWrapper(true, {
                mocks: {
                    $route: { params: {}, query: { ssoError: 'login-failed' } },
                },
            });

            expect(notificationSpy).toHaveBeenCalledWith(
                expect.objectContaining({
                    variant: 'error',
                    message: JSON.stringify(['sw-login.index.ssoErrorGeneric']),
                }),
            );

            notificationSpy.mockRestore();
        });
    });
});
