/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

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
                'sw-password-field': await wrapTestComponent('sw-password-field'),
                'sw-password-field-deprecated': await wrapTestComponent('sw-password-field-deprecated'),
                'router-link': true,
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-alert': await wrapTestComponent('sw-alert', {
                    sync: true,
                }),
                'sw-alert-deprecated': await wrapTestComponent('sw-alert-deprecated', { sync: true }),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-icon': true,
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('module/sw-login/view/sw-login-login/sw-login-login.spec.js', () => {
    let wrapper;

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper(false);
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show a warning if the login is rate limited', async () => {
        wrapper = await createWrapper(false);
        jest.useFakeTimers();
        jest.spyOn(global, 'setTimeout');

        await wrapper.get('#sw-field--username').setValue('Username');
        await wrapper.get('#sw-field--password').setValue('Password');

        expect(wrapper.find('.sw-alert').exists()).toBe(false);

        await wrapper.get('.sw-login-login').trigger('submit');

        await flushPromises();

        // first call is emitting the `login-error`, the second is the timeout that clears the warning
        expect(setTimeout).toHaveBeenCalledTimes(2);
        expect(setTimeout).toHaveBeenLastCalledWith(expect.any(Function), 1000);

        expect(wrapper.get('.sw-alert__message').text()).toBe('["sw-login.index.messageAuthThrottled",0,{"seconds":1}]');

        // advance the timer to make the warning disappear
        jest.advanceTimersByTime(1001);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-alert').exists()).toBe(false);
    });

    it('should handle login', async () => {
        wrapper = await createWrapper(true);

        const username = wrapper.find('#sw-field--username');
        await username.setValue('admin');

        const password = wrapper.find('#sw-field--password');
        await password.setValue('admin');

        const rememberMeCheckbox = wrapper.find('.sw-field--checkbox input');
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
