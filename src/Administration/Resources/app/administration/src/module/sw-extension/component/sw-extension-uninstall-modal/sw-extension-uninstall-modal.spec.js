import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-extension-uninstall-modal', { sync: true }), {
        global: {
            mocks: {
                $t: (path, values) => {
                    if (values) {
                        return path + Object.values(values);
                    }

                    return path;
                },
            },
            stubs: {
                'sw-modal': true,
                'sw-button': true,
                'sw-switch-field': true,
            },
            provide: {},
        },
        props: {
            extensionName: 'Sample extension',
            isLicensed: true,
            isLoading: false,
            ...propsData,
        },
    });
}

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-extension-uninstall-modal', () => {
    it('should show the correct title', async () => {
        const wrapper = await createWrapper();

        // eslint-disable-next-line max-len
        expect(wrapper.vm.title).toBe('sw-extension-store.component.sw-extension-uninstall-modal.titleSample extension');
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

    it('should emit the uninstall extension event', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted()).not.toHaveProperty('uninstall-extension');

        await wrapper.vm.emitUninstall();

        expect(wrapper.emitted()).toHaveProperty('uninstall-extension', [
            [false],
        ]);
    });
});
