import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-extension-removal-modal', { sync: true }), {
        global: {
            mocks: {
                $t: (key, values) => {
                    return key + JSON.stringify(Object.values(values));
                },
            },
            stubs: {
                'sw-button': true,
            },
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
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-extension-removal-modal', () => {
    it('should show the correct title', async () => {
        const wrapper = await createWrapper();

        let title = wrapper.vm.title;

        // eslint-disable-next-line max-len
        expect(title).toBe('sw-extension-store.component.sw-extension-removal-modal.titleCancel["Awesome extension"]');

        await wrapper.setProps({
            isLicensed: false,
        });

        title = wrapper.vm.title;
        // eslint-disable-next-line max-len
        expect(title).toBe('sw-extension-store.component.sw-extension-removal-modal.titleRemove["Awesome extension"]');
    });

    it('should show the correct alert text', async () => {
        const wrapper = await createWrapper();

        let alert = wrapper.vm.alert;

        // eslint-disable-next-line max-len
        expect(alert).toBe('sw-extension-store.component.sw-extension-removal-modal.alertCancel');

        await wrapper.setProps({
            isLicensed: false,
        });

        alert = wrapper.vm.alert;
        // eslint-disable-next-line max-len
        expect(alert).toBe('sw-extension-store.component.sw-extension-removal-modal.alertRemove');
    });

    it('should show the correct button label', async () => {
        const wrapper = await createWrapper();

        let btnLabel = wrapper.vm.btnLabel;

        // eslint-disable-next-line max-len
        expect(btnLabel).toBe('sw-extension-store.component.sw-extension-removal-modal.labelCancel');

        await wrapper.setProps({
            isLicensed: false,
        });

        btnLabel = wrapper.vm.alert;
        // eslint-disable-next-line max-len
        expect(btnLabel).toBe('sw-extension-store.component.sw-extension-removal-modal.alertRemove');
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
