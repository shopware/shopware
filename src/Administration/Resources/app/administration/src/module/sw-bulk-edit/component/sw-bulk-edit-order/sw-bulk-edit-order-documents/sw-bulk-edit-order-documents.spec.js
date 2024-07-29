/**
 * @package services-settings
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-bulk-edit-order-documents', { sync: true }), {
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container'),
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
        },
        props: {
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
