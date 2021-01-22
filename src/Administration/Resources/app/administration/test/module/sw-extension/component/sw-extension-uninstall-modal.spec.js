import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-extension-uninstall-modal';

function createWrapper(propsData = {}) {
    return shallowMount(Shopware.Component.build('sw-extension-uninstall-modal'), {
        propsData: {
            extensionName: 'Sample extension',
            isLicensed: true,
            isLoading: false,
            ...propsData
        },
        mocks: {
            $t: (path, values) => {
                if (values) {
                    return path + Object.values(values);
                }

                return path;
            },
            $tc: v => v
        },
        stubs: {
            'sw-modal': true,
            'sw-button': true,
            'sw-switch-field': true
        },
        provide: {}
    });
}

describe('src/module/sw-extension/component/sw-extension-uninstall-modal', () => {
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
        // eslint-disable-next-line max-len
        expect(wrapper.vm.title).toEqual('sw-extension-store.component.sw-extension-uninstall-modal.titleSample extension');
    });

    it('should not emit the close event when is loading', async () => {
        await wrapper.setProps({
            isLoading: true
        });

        expect(wrapper.emitted()).toEqual({});

        await wrapper.vm.emitClose();

        expect(wrapper.emitted()).toEqual({});
    });

    it('should emit the uninstall extension event', async () => {
        expect(wrapper.emitted()).toEqual({});

        await wrapper.vm.emitUninstall();

        expect(wrapper.emitted()).toEqual({
            'uninstall-extension': [[false]]
        });
    });
});
