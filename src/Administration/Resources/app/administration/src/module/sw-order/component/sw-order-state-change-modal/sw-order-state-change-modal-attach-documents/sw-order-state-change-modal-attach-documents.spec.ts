/**
 * @sw-package checkout
 */
import { mount, type VueWrapper } from '@vue/test-utils';

const STORAGE_KEY = 'sw-order-state-change-modal-send-mail';

let mountedWrapper: VueWrapper | undefined;

async function createWrapper() {
    const wrapper = mount(await wrapTestComponent('sw-order-state-change-modal-attach-documents', { sync: true }), {
        props: {
            order: { id: 'orderId' },
            isLoading: false,
        },
        global: {
            stubs: {
                'mt-switch': true,
                'sw-order-document-card': true,
                'mt-textarea': true,
            },
        },
    });

    mountedWrapper = wrapper;

    return wrapper;
}

describe('sw-order-state-change-modal-attach-documents', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    afterEach(() => {
        mountedWrapper?.unmount();
        mountedWrapper = undefined;
    });

    it('should default sendMail to true without stored value', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.sendMail).toBe(true);
    });

    it('should load the persisted value on mount', async () => {
        localStorage.setItem(STORAGE_KEY, 'false');

        const wrapper = await createWrapper();

        expect(wrapper.vm.sendMail).toBe(false);
    });

    it('should fall back to true for any stored value other than "false"', async () => {
        localStorage.setItem(STORAGE_KEY, 'garbage');

        const wrapper = await createWrapper();

        expect(wrapper.vm.sendMail).toBe(true);
    });

    it('should persist false to local storage', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({ sendMail: false });

        expect(localStorage.getItem(STORAGE_KEY)).toBe('false');
    });

    it('should remove the stored value when sendMail is set back to true', async () => {
        localStorage.setItem(STORAGE_KEY, 'false');

        const wrapper = await createWrapper();
        expect(wrapper.vm.sendMail).toBe(false);

        await wrapper.setData({ sendMail: true });

        expect(localStorage.getItem(STORAGE_KEY)).toBeNull();
    });
});
