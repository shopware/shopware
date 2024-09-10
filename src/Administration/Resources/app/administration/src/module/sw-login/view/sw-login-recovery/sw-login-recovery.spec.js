/**
 * @package admin
 */

import { config, mount } from '@vue/test-utils';

import 'src/module/sw-login/view/sw-login-recovery';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-alert';

async function createWrapper() {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.$route;

    return mount(await Shopware.Component.build('sw-login-recovery'), {
        global: {
            mocks: {
                $tc: (...args) => JSON.stringify([...args]),
                $router: { push: jest.fn() },
            },
            provide: {
                userRecoveryService: {
                    createRecovery: () => {
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
                    template: '<div><input id="email" :value="value" @input="ev => $emit(`input`, ev.target.value)"></input></div>',
                },
                'sw-contextual-field': true,
                'router-link': true,
                'sw-button': await Shopware.Component.build('sw-button'),
                'sw-alert': await Shopware.Component.build('sw-alert'),
                'sw-icon': true,
                'sw-button-deprecated': true,
            },
        },
    });
}

describe('module/sw-login/recovery.spec.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should redirect on submit', async () => {
        await wrapper.get('#email').setValue('test@example.com');

        expect(wrapper.find('.sw-alert').exists()).toBe(false);

        await wrapper.get('.sw-login__recovery-form').trigger('submit');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$router.push).toHaveBeenLastCalledWith({
            name: 'sw.login.index.recoveryInfo',
            params: {
                waitTime: 1,
            },
        });
    });
});
