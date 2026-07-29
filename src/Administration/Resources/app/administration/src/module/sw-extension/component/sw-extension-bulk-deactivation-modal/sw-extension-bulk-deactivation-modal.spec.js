import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-extension-bulk-deactivation-modal', { sync: true }), {
        global: {
            mocks: {
                $t: (path, named, choice) => (typeof choice === 'number' ? `${path}${choice}` : path),
            },
            stubs: {
                'sw-modal': true,
            },
            provide: {},
        },
        props: {
            extensions: [
                { name: 'a', label: 'Extension A' },
                { name: 'b', label: 'Extension B' },
            ],
            isLoading: false,
            ...propsData,
        },
    });
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/component/sw-extension-bulk-deactivation-modal', () => {
    it('should expose the rented extension cuont', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.count).toBe(2);
    });

    it('should show the correct title', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.title).toBe('sw-extension.my-extensions.bulk.deactivationModal.title2');
    });

    it('should not emit the close event when is loading', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            isLoading: true,
        });

        expect(wrapper.emitted()).not.toHaveProperty('modal-close');

        await wrapper.vm.emitClose();

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted()).not.toHaveProperty('modal-close');
    });

    it('should emit the modal close event', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted()).not.toHaveProperty('modal-close');

        await wrapper.vm.emitClose();

        expect(wrapper.emitted()).toHaveProperty('modal-close');
    });

    it('should emit the confirm event', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted()).not.toHaveProperty('confirm');

        await wrapper.vm.emitConfirm();

        expect(wrapper.emitted()).toHaveProperty('confirm');
    });
});
