/**
 * @package system-settings
 */
import { shallowMount } from '@vue/test-utils_v2';
import swBulkEditOrderDocuments from 'src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents';

Shopware.Component.register('sw-bulk-edit-order-documents', swBulkEditOrderDocuments);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-bulk-edit-order-documents'), {
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

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should search for document types when component created', async () => {
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
        expect(wrapper.find('sw-checkbox-field-stub').attributes().disabled).toBeUndefined();
        expect(wrapper.find('sw-switch-field-stub').attributes().disabled).toBeUndefined();
    });
});
