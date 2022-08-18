import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-bulk-edit-order-documents'), {
        stubs: {
            'sw-container': true,
            'sw-checkbox-field': true,
            'sw-switch-field': true,
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve([]),
                    };
                },
            },
        },
        propsData: {
            documents: {
                disabled: false,
            },
            value: {
                documentType: {},
                skipSentDocuments: true,
            },
        },
    });
}

describe('sw-bulk-edit-order-documents', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should search for document types when component created', () => {
        wrapper.vm.documentTypeRepository.search = jest.fn().mockReturnValue(Promise.resolve([]));

        wrapper.vm.createdComponent();

        expect(wrapper.vm.documentTypeRepository.search).toHaveBeenCalled();
        wrapper.vm.documentTypeRepository.search.mockRestore();
    });

    it('should disable document types correctly', async () => {
        await wrapper.setData({
            documentTypes: [
                {
                    name: 'Invoice',
                    technicalName: 'invoice',
                },
            ],
        });
        await wrapper.setProps({
            documents: {
                disabled: true,
            },
        });
        expect(wrapper.find('sw-checkbox-field-stub').attributes().disabled).toBeTruthy();
        expect(wrapper.find('sw-switch-field-stub').attributes().disabled).toBeTruthy();

        await wrapper.setProps({
            documents: {
                disabled: false,
            },
        });
        expect(wrapper.find('sw-checkbox-field-stub').attributes().disabled).toBe(undefined);
        expect(wrapper.find('sw-switch-field-stub').attributes().disabled).toBe(undefined);
    });
});
