/**
 * @sw-package framework
 */

import { config, mount } from '@vue/test-utils';

import 'src/module/sw-login/view/sw-login-recovery';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-alert';

async function createWrapper(validateEmailAddress = null) {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.$route;

    return mount(await wrapTestComponent('sw-login-recovery', { sync: true }), {
        global: {
            mocks: {
                $t: (...args) => JSON.stringify([...args]),
                $router: { push: jest.fn() },
            },
            provide: {
                userService: {},
                licenseViolationService: {},
                validationApiService: {
                    validateEmailAddress:
                        validateEmailAddress ??
                        ((arg) => {
                            if (arg.includes('invalid')) {
                                return Promise.resolve(false);
                            }

                            return Promise.resolve(true);
                        }),
                },
            },
            stubs: {
                'router-view': true,
                'sw-loader': true,
                'sw-text-field': {
                    props: {
                        value: {
                            required: true,
                            type: String,
                        },
                    },
                    template:
                        '<div><input id="email" :value="value" @input="ev => $emit(`input`, ev.target.value)"></input></div>',
                },
                'sw-contextual-field': true,
                'router-link': true,
            },
        },
    });
}

describe('module/sw-login/recovery.spec.js', () => {
    let wrapper;

    beforeEach(async () => {
        if (!Shopware.Service('userRecoveryService')) {
            Shopware.Service().register('userRecoveryService', () => {
                return {
                    createRecovery: () => Promise.resolve(),
                };
            });
        }

        window.history.replaceState({}, '');
        wrapper = await createWrapper();
    });

    it('should redirect to the recovery info on submit', async () => {
        Shopware.Service('userRecoveryService').createRecovery = jest.fn(() => Promise.resolve());

        await wrapper.get('input').setValue('test@example.com');

        await wrapper.get('.sw-login__recovery-form').trigger('submit');

        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenLastCalledWith({
            name: 'sw.login.index.recoveryInfo',
            state: {
                email: 'test@example.com',
            },
        });
    });

    it('should show an inline warning instead of redirecting when the request is rate limited', async () => {
        jest.useFakeTimers();

        Shopware.Service('userRecoveryService').createRecovery = jest.fn(() =>
            Promise.reject({
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
            }),
        );

        await wrapper.get('input').setValue('test@example.com');

        expect(wrapper.find('[role="banner"]').exists()).toBe(false);

        await wrapper.get('.sw-login__recovery-form').trigger('submit');

        await flushPromises();

        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();
        expect(wrapper.get('[role="banner"]').text()).toBe(
            '["global.error-codes.FRAMEWORK__RATE_LIMIT_EXCEEDED",{"seconds":1},0]',
        );

        jest.advanceTimersByTime(1001);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="banner"]').exists()).toBe(false);

        jest.useRealTimers();
    });

    it('should fall back to a 10 second countdown when the rate limit response has no wait time', async () => {
        Shopware.Service('userRecoveryService').createRecovery = jest.fn(() =>
            Promise.reject({
                response: {
                    data: {
                        errors: {
                            status: 429,
                        },
                    },
                },
            }),
        );

        await wrapper.get('input').setValue('test@example.com');

        await wrapper.get('.sw-login__recovery-form').trigger('submit');

        await flushPromises();

        expect(wrapper.get('[role="banner"]').text()).toBe(
            '["global.error-codes.FRAMEWORK__RATE_LIMIT_EXCEEDED",{"seconds":10},0]',
        );
    });

    it('should stay on the form when the email validation request fails', async () => {
        wrapper = await createWrapper(
            jest.fn(() =>
                Promise.reject({
                    response: {
                        data: {
                            errors: {
                                status: 500,
                            },
                        },
                    },
                }),
            ),
        );

        await wrapper.get('input').setValue('test@example.com');
        await flushPromises();

        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();
        expect(wrapper.find('[role="banner"]').exists()).toBe(false);
        expect(wrapper.find('.mt-button--primary').wrapperElement).toBeDisabled();
    });

    it('should redirect to the recovery info on other errors without revealing the account state', async () => {
        Shopware.Service('userRecoveryService').createRecovery = jest.fn(() =>
            Promise.reject({
                response: {
                    data: {
                        errors: {
                            status: 500,
                        },
                    },
                },
            }),
        );

        await wrapper.get('input').setValue('test@example.com');

        await wrapper.get('.sw-login__recovery-form').trigger('submit');

        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenLastCalledWith({
            name: 'sw.login.index.recoveryInfo',
            state: {
                email: 'test@example.com',
            },
        });
    });

    it('should show the expired link error when routed with the linkExpired state', async () => {
        window.history.replaceState({ linkExpired: true }, '');

        wrapper = await createWrapper();

        expect(wrapper.get('[role="banner"]').text()).toBe('["sw-login.recovery.messageLinkExpired"]');
    });

    it('button should be disabled until enter a valid email address', async () => {
        await wrapper.get('input').setValue('invalid@email');
        await flushPromises();

        const button = await wrapper.find('.mt-button--primary');
        expect(button.wrapperElement).toBeDisabled();

        await wrapper.get('input').setValue('valid@email.sw');
        await flushPromises();

        expect(button.wrapperElement).toBeEnabled();
    });
});
