/**
 * @sw-package checkout
 */
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

const documentTypesFixtures = [
    {
        id: 'invoice-id',
        technicalName: 'invoice',
        name: 'Invoice',
    },
];

async function createWrapper() {
    return mount(await wrapTestComponent('sw-bulk-edit-order-documents', { sync: true }), {
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-checkbox-field': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve([...documentTypesFixtures]),
                        };
                    },
                },
                documentV2Service: {
                    getAvailableDocumentTypes: jest.fn().mockResolvedValue({
                        invoice: { formats: ['pdf'] },
                    }),
                    getDocumentTypeLabel: (technicalName) =>
                        `sw-order.components.createDocumentModal.documentTypes.${technicalName}`,
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
        global.activeFeatureFlags = [];
        wrapper = await createWrapper();
    });

    it('should search for document types when component created', async () => {
        wrapper.vm.documentTypeRepository.search = jest.fn().mockReturnValue(Promise.resolve([]));

        wrapper.vm.createdComponent();

        expect(wrapper.vm.documentTypeRepository.search).toHaveBeenCalled();
        wrapper.vm.documentTypeRepository.search.mockRestore();
    });

    it('should disable document types correctly', async () => {
        wrapper.vm.documentTypes = [
            {
                name: 'Invoice',
                technicalName: 'invoice',
            },
        ];
        await nextTick();
        await wrapper.setProps({
            documents: {
                disabled: true,
            },
        });

        expect(wrapper.findComponent('.mt-field--checkbox__container').props().disabled).toBe(true);
        expect(wrapper.findComponent('.mt-switch').props().disabled).toBeDefined();

        await wrapper.setProps({
            documents: {
                disabled: false,
            },
        });
        expect(wrapper.findComponent('.mt-field--checkbox__container').props().disabled).toBe(false);
        expect(wrapper.findComponent('.mt-switch').props().disabled).toBeUndefined();
    });

    it('fetches document types from the available types endpoint', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.documentTypes).toEqual([
            {
                id: 'invoice',
                technicalName: 'invoice',
                name: 'sw-order.components.createDocumentModal.documentTypes.invoice',
            },
        ]);
    });
});
