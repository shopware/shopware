import { shallowMount } from '@vue/test-utils';
import swExtensionDeactivationModal from 'src/module/sw-extension/component/sw-extension-deactivation-modal';

Shopware.Component.register('sw-extension-deactivation-modal', swExtensionDeactivationModal);

async function createWrapper(propsData = {}) {
    return shallowMount(await Shopware.Component.build('sw-extension-deactivation-modal'), {
        propsData: {
            extensionName: 'Sample extension',
            isLicensed: true,
            isLoading: false,
            ...propsData,
        },
        mocks: {
            $tc: (path, choice, values) => {
                if (values) {
                    return path + Object.values(values);
                }

                return path;
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
 * @package services-settings
 */
describe('src/module/sw-extension/component/sw-extension-deactivation-modal', () => {
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

    it('should show the correct remove hint (is licensed)', async () => {
        // eslint-disable-next-line max-len
        expect(wrapper.vm.removeHint).toBe('sw-extension-store.component.sw-extension-deactivation-modal.descriptionCancelsw-extension-store.component.sw-extension-card-base.contextMenu.cancelAndRemoveLabel');
    });

    it('should show the correct remove hint (is not licensed)', async () => {
        await wrapper.setProps({
            isLicensed: false,
        });
        // eslint-disable-next-line max-len
        expect(wrapper.vm.removeHint).toBe('sw-extension-store.component.sw-extension-deactivation-modal.descriptionCancelsw-extension-store.component.sw-extension-card-base.contextMenu.removeLabel');
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

    it('should emit the deactivate extension event', async () => {
        expect(wrapper.emitted()).toEqual({});

        await wrapper.vm.emitDeactivate();

        expect(wrapper.emitted()).toEqual({
            'extension-deactivate': [[]],
        });
    });
});
