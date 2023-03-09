import 'src/module/sw-inactivity-login/page/index/index';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/form/sw-password-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import { BroadcastChannel } from 'worker_threads';
import { shallowMount } from '@vue/test-utils';

// eslint-disable-next-line no-undef
async function createWrapper(routerPushImplementation = jest.fn(), loginByUsername = jest.fn()): Promise<Wrapper<Vue>> {
    return shallowMount(await Shopware.Component.build('sw-inactivity-login'), {
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-icon': true,
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-loader': await Shopware.Component.build('sw-loader'),
            'sw-password-field': await Shopware.Component.build('sw-password-field'),
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
        },
        mocks: {
            $router: {
                push: routerPushImplementation,
            }
        },
        provide: {
            loginService: {
                loginByUsername,
            },
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {},
            },
            validationService: {},
        },
        propsData: {
            hash: 'foo',
        },
        attachTo: document.body,
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
    });

    afterAll(() => {
        Object.defineProperty(window, 'location', { configurable: true, value: original });
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

        expect(push).toBeCalledTimes(1);
        expect(push).toBeCalledWith({
            name: 'sw.login.index'
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

        expect(loginByUserName).toBeCalledTimes(1);
        expect(loginByUserName).toBeCalledWith('max', '');
        expect(push).toBeCalledTimes(1);
        expect(push).toBeCalledWith('sw.example.route.index');
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

        expect(loginByUserName).toBeCalledTimes(1);
        expect(loginByUserName).toBeCalledWith('max', '');

        // @ts-expect-error
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

        expect(push).toBeCalledTimes(1);
        expect(push).toBeCalledWith({
            name: 'sw.login.index',
        });
    });

    it('should redirect on valid channel message', async () => {
        const push = jest.fn();
        sessionStorage.setItem('lastKnownUser', 'max');
        const wrapper = await createWrapper(push);
        await flushPromises();

        const channel = new BroadcastChannel('session_channel');
        channel.postMessage({
            inactive: false,
        });

        await flushPromises();

        expect(push).toBeCalledTimes(1);

        channel.close();
    });

    it('should not redirect on invalid channel message', async () => {
        const push = jest.fn();
        sessionStorage.setItem('lastKnownUser', 'max');
        const wrapper = await createWrapper(push);
        await flushPromises();

        const channel = new BroadcastChannel('session_channel');
        channel.postMessage(null);
        channel.postMessage({});
        channel.postMessage({
            inactive: true,
        });

        await flushPromises();

        expect(push).toBeCalledTimes(0);

        channel.close();
    });
});
