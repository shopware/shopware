import { mount } from '@vue/test-utils';
import { reloadPage } from 'src/core/helper/navigation.helper';

jest.mock('src/core/helper/navigation.helper', () => ({
    reloadPage: jest.fn(),
}));

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

        wrapper = await createWrapper();
        expect(wrapper.find('.sw-login').attributes('style')).toBe('display: none;');
    });

    it('should not trigger reload when "refresh-after-logout" storage key is not set', async () => {
        wrapper = await createWrapper();

        expect(reloadPage).not.toHaveBeenCalled();
    });

    it('should trigger reload when "refresh-after-logout" storage key is set to true', async () => {
        sessionStorage.setItem('refresh-after-logout', 'true');
        wrapper = await createWrapper();

        expect(reloadPage).toHaveBeenCalled();
    });
});
