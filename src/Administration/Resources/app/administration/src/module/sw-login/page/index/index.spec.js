import { mount } from '@vue/test-utils';

async function createWrapper(methodOverrides = {}) {
    const swLogin = await wrapTestComponent('sw-login', {
        sync: true,
    });

    const componentConfig =
        Object.keys(methodOverrides).length > 0
            ? { ...swLogin, methods: { ...swLogin.methods, ...methodOverrides } }
            : swLogin;

    return mount(componentConfig, {
        global: {
            stubs: {
                'router-view': true,
                'sw-loader': true,
            },
            mocks: {},
        },
    });
}

/**
 * @sw-package framework
 */
describe('src/module/sw-login/page/index/index.js', () => {
    let wrapper;

    beforeEach(async () => {
        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        sessionStorage.removeItem('refresh-after-logout');
        await flushPromises();
    });

    it('should render the component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.find('.sw-login').attributes('style')).toBeUndefined();
    });

    it('should not render the component', async () => {
        sessionStorage.setItem('refresh-after-logout', 'true');

        wrapper = await createWrapper({ _reloadPage: jest.fn() });
        expect(wrapper.find('.sw-login').attributes('style')).toBe('display: none;');
    });

    it('should not trigger reload when "refresh-after-logout" storage key is not set', async () => {
        const reloadSpy = jest.fn();

        wrapper = await createWrapper({ _reloadPage: reloadSpy });

        expect(reloadSpy).not.toHaveBeenCalled();
    });

    it('should trigger reload when "refresh-after-logout" storage key is set to true', async () => {
        sessionStorage.setItem('refresh-after-logout', 'true');

        const reloadSpy = jest.fn();

        wrapper = await createWrapper({ _reloadPage: reloadSpy });

        expect(reloadSpy).toHaveBeenCalled();
    });
});
