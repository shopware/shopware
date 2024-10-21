import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(
        await wrapTestComponent('sw-extension-deactivation-modal', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $tc: (path, choice, values) => {
                        if (values) {
                            return path + Object.values(values);
                        }

                        return path;
                    },
                },
                stubs: {
                    'sw-button': true,
                },
            },
            props: {
                extensionName: 'Sample extension',
                isLicensed: true,
                isLoading: false,
                ...propsData,
            },
        },
    );
}

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-extension-deactivation-modal', () => {
    it('should show the correct remove hint (is licensed)', async () => {
        const wrapper = await createWrapper();

        // eslint-disable-next-line max-len
        expect(wrapper.vm.removeHint).toBe(
            'sw-extension-store.component.sw-extension-deactivation-modal.descriptionCancelsw-extension-store.component.sw-extension-card-base.contextMenu.cancelAndRemoveLabel',
        );
    });

    it('should show the correct remove hint (is not licensed)', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            isLicensed: false,
        });
        // eslint-disable-next-line max-len
        expect(wrapper.vm.removeHint).toBe(
            'sw-extension-store.component.sw-extension-deactivation-modal.descriptionCancelsw-extension-store.component.sw-extension-card-base.contextMenu.removeLabel',
        );
    });

    it('should emit the close event', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.emitted()).not.toHaveProperty('modal-close');

        await wrapper.vm.emitClose();
        expect(wrapper.emitted()).toHaveProperty('modal-close');
    });

    it('should not emit the close event when is loading', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            isLoading: true,
        });
        expect(wrapper.emitted()).not.toHaveProperty('modal-close');

        await wrapper.vm.emitClose();
        expect(wrapper.emitted()).not.toHaveProperty('modal-close');
    });

    it('should emit the deactivate extension event', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.emitted()).not.toHaveProperty('extension-deactivate');

        await wrapper.vm.emitDeactivate();
        expect(wrapper.emitted()).toHaveProperty('extension-deactivate');
    });
});
