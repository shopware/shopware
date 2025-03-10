/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import useSystem from '../../../../app/composables/use-system';

async function createWrapper(loginSuccessfull) {
    const wrapper = mount(await wrapTestComponent('sw-login-login', { sync: true }), {
        global: {
            mocks: {
                $tc: (...args) => JSON.stringify([...args]),
            },
            provide: {
                loginService: {
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

    const passwordInput = wrapper.findByLabel('["sw-login.index.labelPassword"]');
    const usernameInput = wrapper.get('#sw-field--username');
    const rememberMeCheckbox = wrapper.find('.mt-field--checkbox__container input');

    return { wrapper, passwordInput, usernameInput, rememberMeCheckbox };
}

describe('module/sw-login/view/sw-login-login/sw-login-login.spec.js', () => {
    beforeAll(() => {
        useSystem().locales.value.push(navigator.language);
    });

    it('should be a Vue.js component', async () => {
        const { wrapper } = await createWrapper(false);
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
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
});
