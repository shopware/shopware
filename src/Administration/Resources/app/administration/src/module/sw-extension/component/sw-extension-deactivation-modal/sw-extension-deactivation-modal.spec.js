import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(
        await wrapTestComponent('sw-extension-deactivation-modal', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $t: (path, values) => {
                        if (values) {
                            return path + Object.values(values);
                        }

                        return path;
                    },
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
 * @sw-package checkout
 */
describe('src/module/sw-extension/component/sw-extension-deactivation-modal', () => {
    it.each([
        {
            extension: 'a licensed extension',
            props: {},
            label: 'sw-extension-store.component.sw-extension-card-base.contextMenu.cancelAndRemoveLabel',
        },
        {
            extension: 'a licensed extension without deletable files',
            props: { hasDeletableFiles: false },
            label: 'sw-extension-store.component.sw-extension-card-base.contextMenu.cancelSubscriptionLabel',
        },
        {
            extension: 'an unlicensed extension',
            props: { isLicensed: false },
            label: 'global.default.remove',
        },
    ])('should name the matching context menu action in the hint for $extension', async ({ props, label }) => {
        const wrapper = await createWrapper(props);

        expect(wrapper.get('.sw-extension-deactivation-modal__hint-paragraph').text()).toBe(
            `sw-extension-store.component.sw-extension-deactivation-modal.descriptionCancel${label}`,
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
