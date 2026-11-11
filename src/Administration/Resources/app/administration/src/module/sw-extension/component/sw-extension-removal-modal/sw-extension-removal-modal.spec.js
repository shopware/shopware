import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-extension-removal-modal', { sync: true }), {
        global: {
            mocks: {
                $t: (key, values) => {
                    return values ? key + JSON.stringify(Object.values(values)) : key;
                },
            },
            stubs: {},
        },
        props: {
            extensionName: 'Awesome extension',
            isLicensed: true,
            isLoading: false,
            ...propsData,
        },
    });
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/component/sw-extension-removal-modal', () => {
    it.each([
        {
            action: 'cancels the subscription and removes the extension',
            props: { isLicensed: true, hasDeletableFiles: true },
            title: 'titleCancel["Awesome extension"]',
            alert: 'alertCancel',
            button: 'sw-extension-store.component.sw-extension-removal-modal.labelCancel',
            explainsRemoval: true,
        },
        {
            action: 'only cancels the subscription',
            props: { isLicensed: true, hasDeletableFiles: false },
            title: 'titleCancelSubscription["Awesome extension"]',
            alert: 'alertCancelSubscription',
            button: 'sw-extension-store.component.sw-extension-removal-modal.labelCancelSubscription',
            explainsRemoval: false,
        },
        {
            action: 'only removes the extension',
            props: { isLicensed: false, hasDeletableFiles: true },
            title: 'titleRemove["Awesome extension"]',
            alert: 'alertRemove',
            button: 'global.default.remove',
            explainsRemoval: true,
        },
    ])('should word the modal for an action that $action', async ({ props, title, alert, button, explainsRemoval }) => {
        const wrapper = await createWrapper(props);

        expect(wrapper.attributes('title')).toBe(`sw-extension-store.component.sw-extension-removal-modal.${title}`);
        expect(wrapper.get('.sw-extension-removal-modal__bold-paragraph').text()).toBe(
            `sw-extension-store.component.sw-extension-removal-modal.${alert}`,
        );
        expect(wrapper.findByText('button', button)).not.toBeNull();
        expect(wrapper.text().includes('sw-extension-store.component.sw-extension-removal-modal.description')).toBe(
            explainsRemoval,
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

    it('should emit the remove extension event', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted()).not.toHaveProperty('remove-extension');

        await wrapper.vm.emitRemoval();

        expect(wrapper.emitted()).toHaveProperty('remove-extension');
    });
});
