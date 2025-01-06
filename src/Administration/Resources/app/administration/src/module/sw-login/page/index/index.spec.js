import { mount } from '@vue/test-utils';

async function createWrapper() {
    const swLogin = await wrapTestComponent('sw-login', {
        sync: true,
    });

    return mount(swLogin, {
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
 * @package admin
 */
describe('src/module/sw-login/page/index/index.js', () => {
    let wrapper;

    beforeEach(async () => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        sessionStorage.removeItem('refresh-after-logout');
        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.find('.sw-login').attributes('style')).toBeUndefined();
    });

    it('should not render the component', async () => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });

        sessionStorage.setItem('refresh-after-logout', 'true');

        wrapper = await createWrapper();
        expect(wrapper.find('.sw-login').attributes('style')).toBe('display: none;');
    });

    it('should not trigger reload when "refresh-after-logout" storage key is not set', async () => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });

        wrapper = await createWrapper();

        expect(window.location.reload).not.toHaveBeenCalled();
    });

    it('should trigger reload when "refresh-after-logout" storage key is set to true', async () => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });

        sessionStorage.setItem('refresh-after-logout', 'true');
        wrapper = await createWrapper();

        expect(window.location.reload).toHaveBeenCalled();
    });
});
