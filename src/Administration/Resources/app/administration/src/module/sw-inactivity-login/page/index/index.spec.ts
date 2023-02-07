import 'src/module/sw-inactivity-login/page/index/index';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-icon';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/form/sw-password-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import { shallowMount } from '@vue/test-utils';

async function createWrapper(routerPushImplementation = jest.fn(), loginByUsername = jest.fn()): Promise<Wrapper<Vue>> {
    return shallowMount(await Shopware.Component.build('sw-inactivity-login'), {
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-icon': await Shopware.Component.build('sw-icon'),
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
        attachTo: document.body,
    });
}

describe('src/module/sw-inactivity-login/page/index/index.ts', () => {
    const original = window.location;

    beforeAll(() => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });
    });

    afterAll(() => {
        Object.defineProperty(window, 'location', { configurable: true, value: original });
    });

    it('should be a Vue.js component', async () => {
        localStorage.setItem('lastKnownUser', 'max');
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should set data:url as background image', async () => {
        localStorage.setItem('lastKnownUser', 'admin');
        localStorage.setItem('inactivityBackground', 'data:urlFoOBaR');
        const wrapper = await createWrapper();
        await flushPromises();

        const container = wrapper.find('.sw-inactivity-login');
        expect(container.exists()).toBe(true);
        expect((container.element as HTMLElement).style.backgroundImage).toBe('url(data:urlFoOBaR)');
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
        localStorage.setItem('lastKnownUser', 'max');
        sessionStorage.setItem('sw-admin-previous-route', '{ "fullPath": "sw.example.route.index" }');
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
        localStorage.setItem('lastKnownUser', 'max');
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
        localStorage.setItem('lastKnownUser', 'max');
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
});
