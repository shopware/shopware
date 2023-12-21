/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

import 'src/module/sw-login/view/sw-login-login';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-alert';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';

async function createWrapper(loginSuccessful) {
    return mount(await Shopware.Component.build('sw-login-login'), {
        mocks: {
            $tc: (...args) => JSON.stringify([...args]),
        },
        provide: {
            loginService: {
                loginByUsername: () => {
                    if (loginSuccessful) {
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
            },
            userService: {},
            licenseViolationService: {},
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
                template: '<div><input id="username" :value="value" @input="ev => $emit(`input`, ev.target.value)"></input></div>',
            },
            'sw-password-field': {
                props: {
                    value: {
                        required: true,
                        type: String,
                    },
                },
                template: '<div><input id="password" :value="value" @input="ev => $emit(`input`, ev.target.value)"></input></div>',
            },
            'router-link': true,
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-alert': await Shopware.Component.build('sw-alert'),
            'sw-icon': true,
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
        },
    });
}

describe('module/sw-login/login.spec.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper(false);
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show a warning if the login is rate limited', async () => {
        jest.useFakeTimers();
        jest.spyOn(global, 'setTimeout');

        await wrapper.get('#username').setValue('Username');
        await wrapper.get('#password').setValue('Password');

        expect(wrapper.find('.sw-alert').exists()).toBe(false);

        await wrapper.get('.sw-login-login').trigger('submit');

        await wrapper.vm.$nextTick();

        // first call is emitting the `login-error`, the second is the timeout that clears the warning
        expect(setTimeout).toHaveBeenCalledTimes(2);
        expect(setTimeout).toHaveBeenLastCalledWith(expect.any(Function), 1000);

        expect(wrapper.get('.sw-alert').props()).toEqual({ appearance: 'default', closable: false, icon: null, notificationIndex: null, showIcon: true, title: '', variant: 'info' });
        expect(wrapper.get('.sw-alert__message').text()).toBe('["sw-login.index.messageAuthThrottled",0,{"seconds":1}]');

        // advance the timer to make the warning disappear
        jest.advanceTimersByTime(1001);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-alert').exists()).toBe(false);
    });

    it('should handle login', async () => {
        wrapper = await createWrapper(true);

        const username = wrapper.find('#username');
        await username.setValue('admin');

        const password = wrapper.find('#password');
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
