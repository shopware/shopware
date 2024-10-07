import { mount } from '@vue/test-utils';
import { CookieStorage } from 'cookie-storage';

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
    const cookieStorage = new CookieStorage({
        domain: null,
        secure: false, // only allow HTTPs
        sameSite: 'Strict', // Should be Strict
    });

    beforeAll(() => {
        Shopware.Service().register('loginService', () => {
            return {
                getStorage: () => {
                    return cookieStorage;
                },
            };
        });
    });

    beforeEach(async () => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });

        // Clear all cookies
        cookieStorage.clear();
        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

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

        cookieStorage.setItem('refresh-after-logout', true);

        wrapper = await createWrapper();
        expect(wrapper.find('.sw-login').attributes('style')).toBe('display: none;');
    });

    it('should not trigger reload when "refresh-after-logout" cookie is not set', async () => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });

        wrapper = await createWrapper();

        expect(window.location.reload).not.toHaveBeenCalled();
    });

    it('should trigger reload when "refresh-after-logout" cookie is set to true', async () => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: jest.fn() },
        });

        cookieStorage.setItem('refresh-after-logout', true);
        wrapper = await createWrapper();

        expect(window.location.reload).toHaveBeenCalled();
    });
});
