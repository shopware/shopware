import { shallowMount } from '@vue/test-utils';
import swExtensionRemovalModal from 'src/module/sw-extension/component/sw-extension-removal-modal';

Shopware.Component.register('sw-extension-removal-modal', swExtensionRemovalModal);

async function createWrapper(propsData = {}) {
    return shallowMount(await Shopware.Component.build('sw-extension-removal-modal'), {
        propsData: {
            extensionName: 'Awesome extension',
            isLicensed: true,
            isLoading: false,
            ...propsData,
        },
        mocks: {
            $t: (key, values) => {
                return key + JSON.stringify(Object.values(values));
            },
        },
        stubs: {
            'sw-modal': true,
            'sw-button': true,
        },
        provide: {},
    });
}

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/component/sw-extension-removal-modal', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {});

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the correct title', async () => {
        let title = wrapper.vm.title;

        // eslint-disable-next-line max-len
        expect(title).toBe('sw-extension-store.component.sw-extension-removal-modal.titleCancel[\"Awesome extension\"]');

        await wrapper.setProps({
            isLicensed: false,
        });

        title = wrapper.vm.title;
        // eslint-disable-next-line max-len
        expect(title).toBe('sw-extension-store.component.sw-extension-removal-modal.titleRemove[\"Awesome extension\"]');
    });

    it('should show the correct alert text', async () => {
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
        expect(wrapper.emitted()).toEqual({});

        await wrapper.vm.emitClose();

        expect(wrapper.emitted()).toEqual({
            'modal-close': [[]],
        });
    });

    it('should not emit the close event when is loading', async () => {
        await wrapper.setProps({
            isLoading: true,
        });

        expect(wrapper.emitted()).toEqual({});

        await wrapper.vm.emitClose();

        expect(wrapper.emitted()).toEqual({});
    });

    it('should emit the remove extension event', async () => {
        expect(wrapper.emitted()).toEqual({});

        await wrapper.vm.emitRemoval();

        expect(wrapper.emitted()).toEqual({
            'remove-extension': [[]],
        });
    });
});
