import { mount } from '@vue/test-utils';

import 'src/module/sw-login/view/sw-login-login';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-alert';

function createWrapper() {
    return mount(Shopware.Component.build('sw-login-login'), {
        mocks: {
            $tc: (...args) => JSON.stringify([...args])
        },
        provide: {
            loginService: {
                loginByUsername: () => {
                    return new Promise((resolve, reject) => {
                        const response = {
                            config: {
                                url: 'test.test.de'
                            },
                            response: {
                                data: {
                                    errors: {
                                        status: 429,
                                        meta: {
                                            parameters: {
                                                seconds: 1
                                            }
                                        }
                                    }
                                }
                            }
                        };

                        return reject(response);
                    });
                }
            },
            userService: {},
            licenseViolationService: {}
        },
        stubs: {
            'router-view': true,
            'sw-loader': true,
            'sw-text-field': {
                props: {
                    value: {
                        required: true,
                        type: String
                    }
                },
                template: '<div><input id="username" :value="value" @input="ev => $emit(`input`, ev.target.value)"></input></div>'
            },
            'sw-contextual-field': true,
            'sw-password-field': {
                props: {
                    value: {
                        required: true,
                        type: String
                    }
                },
                template: '<div><input id="password" :value="value" @input="ev => $emit(`input`, ev.target.value)"></input></div>'
            },
            'router-link': true,
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-alert': Shopware.Component.build('sw-alert'),
            'sw-icon': true
        }
    });
}

describe('module/sw-login/login.spec.js', () => {
    let wrapper;

    beforeAll(() => {
        global.activeFeatureFlags = ['FEATURE_NEXT_13795'];
    });

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show a warning if the login is rate limited', async () => {
        jest.useFakeTimers();

        wrapper.get('#username').setValue('Username');
        wrapper.get('#password').setValue('Password');

        expect(wrapper.find('.sw-alert').exists()).toBe(false);

        await wrapper.get('.sw-login-login').trigger('submit');

        await wrapper.vm.$nextTick();

        // first call is emitting the `login-error`, the second is the timeout that clears the warning
        expect(setTimeout).toHaveBeenCalledTimes(2);
        expect(setTimeout).toHaveBeenLastCalledWith(expect.any(Function), 1000);

        expect(wrapper.get('.sw-alert').props()).toEqual({ appearance: 'default', closable: false, notificationIndex: null, showIcon: true, title: '', variant: 'info' });
        expect(wrapper.get('.sw-alert__message').text()).toBe('["sw-login.index.messageAuthThrottled",0,{"seconds":1}]');

        // advance the timer to make the warning disappear
        jest.advanceTimersByTime(1001);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-alert').exists()).toBe(false);
    });
});
