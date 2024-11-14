import { BroadcastChannel } from 'worker_threads';
import { mount } from '@vue/test-utils';

/**
 * @package admin
 */
async function createWrapper(routerPushImplementation = jest.fn(), loginByUsername = jest.fn()) {
    return mount(await wrapTestComponent('sw-inactivity-login', { sync: true }), {
        props: {
            hash: 'foo',
        },
        attachTo: document.body,
        global: {
            stubs: {
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                          <slot name="modal-header"></slot>
                          <slot></slot>
                          <slot name="modal-footer"></slot>
                        </div>
                    `,
                },
                'sw-icon': await wrapTestComponent('sw-icon'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-loader': await wrapTestComponent('sw-loader'),
                'sw-password-field': await wrapTestComponent('sw-password-field'),
                'sw-password-field-deprecated': await wrapTestComponent('sw-password-field-deprecated'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'router-link': true,
                'sw-field-copyable': true,
                'sw-icon-deprecated': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-loader-deprecated': true,
            },
            mocks: {
                $router: {
                    push: routerPushImplementation,
                },
            },
            provide: {
                loginService: {
                    loginByUsername,
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
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {},
                },
                validationService: {},
            },
        },
    });
}

describe('src/module/sw-inactivity-login/page/index/index.ts', () => {
    const original = window.location;

    beforeAll(() => {
        // @ts-ignore
        global.BroadcastChannel = BroadcastChannel;

        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });
    });

    afterEach(() => {
        sessionStorage.removeItem('lastKnownUser');
        sessionStorage.removeItem('sw-admin-previous-route_foo');
        localStorage.removeItem('inactivityBackground_foo');
        localStorage.removeItem('rememberMe');
    });

    afterAll(() => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: original,
        });
    });

    it('should be a Vue.js component', async () => {
        sessionStorage.setItem('lastKnownUser', 'max');
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should set data:url as background image', async () => {
        sessionStorage.setItem('lastKnownUser', 'admin');
        localStorage.setItem('inactivityBackground_foo', 'data:urlFoOBaR');
        const wrapper = await createWrapper();
        await flushPromises();

        const container = wrapper.find('.sw-inactivity-login');
        expect(container.exists()).toBe(true);
        expect(container.element.style.backgroundImage).toBe('url(data:urlFoOBaR)');
    });

    it('should push to login without last known user', async () => {
        const push = jest.fn();
        await createWrapper(push);
        await flushPromises();

        expect(push).toHaveBeenCalledTimes(1);
        expect(push).toHaveBeenCalledWith({
            name: 'sw.login.index',
        });
    });

    it('should redirect to previous route on login', async () => {
        const push = jest.fn();
        const loginByUserName = jest.fn(() => {
            return Promise.resolve();
        });
        sessionStorage.setItem('lastKnownUser', 'max');
        sessionStorage.setItem('sw-admin-previous-route_foo', '{ "fullPath": "sw.example.route.index" }');
        const wrapper = await createWrapper(push, loginByUserName);
        await flushPromises();

        const loginButton = wrapper.find('.sw-button');
        await loginButton.trigger('click');

        expect(loginByUserName).toHaveBeenCalledTimes(1);
        expect(loginByUserName).toHaveBeenCalledWith('max', '');
        expect(push).toHaveBeenCalledTimes(1);
        expect(push).toHaveBeenCalledWith('sw.example.route.index');
    });

    it('should show password error on failed login attempt', async () => {
        const loginByUserName = jest.fn(() => {
            return Promise.reject();
        });
        sessionStorage.setItem('lastKnownUser', 'max');
        const wrapper = await createWrapper(jest.fn(), loginByUserName);
        await flushPromises();

        const loginButton = wrapper.find('.sw-button');
        await loginButton.trigger('click');
        await flushPromises();

        expect(loginByUserName).toHaveBeenCalledTimes(1);
        expect(loginByUserName).toHaveBeenCalledWith('max', '');

        expect(wrapper.vm.passwordError !== null).toBe(true);
        const passwordError = wrapper.find('.sw-field__error');
        expect(passwordError.exists()).toBe(true);
    });

    it('should navigate back to login', async () => {
        sessionStorage.setItem('lastKnownUser', 'max');
        const push = jest.fn();
        const wrapper = await createWrapper(push);
        await flushPromises();

        const backLink = wrapper.find('a');
        await backLink.trigger('click');

        expect(push).toHaveBeenCalledTimes(1);
        expect(push).toHaveBeenCalledWith({
            name: 'sw.login.index',
        });
    });

    it('should redirect on valid channel message', async () => {
        const push = jest.fn();
        sessionStorage.setItem('lastKnownUser', 'max');
        await createWrapper(push);
        await flushPromises();

        const channel = new BroadcastChannel('session_channel');
        channel.postMessage({
            inactive: false,
        });

        await flushPromises();

        expect(push).toHaveBeenCalledTimes(1);

        channel.close();
    });

    it('should not redirect on invalid channel message', async () => {
        const push = jest.fn();
        sessionStorage.setItem('lastKnownUser', 'max');
        await createWrapper(push);
        await flushPromises();

        const channel = new BroadcastChannel('session_channel');
        channel.postMessage(null);
        channel.postMessage({});
        channel.postMessage({
            inactive: true,
        });

        await flushPromises();

        expect(push).toHaveBeenCalledTimes(0);

        channel.close();
    });

    it('should remember user', async () => {
        const push = jest.fn();
        sessionStorage.setItem('lastKnownUser', 'max');
        sessionStorage.setItem('sw-admin-previous-route_foo', '{ "fullPath": "sw.example.route.index" }');
        const wrapper = await createWrapper(
            push,
            jest.fn(() => Promise.resolve()),
        );
        await flushPromises();

        const rememberMe = wrapper.find('.sw-field--checkbox input');
        await rememberMe.trigger('click');

        const loginButton = wrapper.find('.sw-button');
        await loginButton.trigger('click');

        expect(push).toHaveBeenCalledTimes(1);
        expect(push).toHaveBeenCalledWith('sw.example.route.index');

        const expectedDuration = new Date();
        expectedDuration.setDate(expectedDuration.getDate() + 14);
        const rememberMeDuration = Number(localStorage.getItem('rememberMe'));
        expect(rememberMeDuration).toBeGreaterThan(1600000);
        expect(rememberMeDuration).toBeLessThanOrEqual(+expectedDuration);
    });
});
