/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

const documentTypesFixtures = [
    {
        id: 'invoice-id',
        technicalName: 'invoice',
        translated: { name: 'Invoice' },
    },
];

async function createWrapper() {
    return mount(await wrapTestComponent('sw-bulk-edit-order-documents-download-documents', { sync: true }), {
        global: {
            stubs: {
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
                    getDocumentTypeSnippet: (technicalName) =>
                        `sw-order.components.createDocumentModal.documentTypes.${technicalName}`,
                },
            },
        },
    });
}

describe('sw-bulk-edit-order-documents-download-documents', () => {
    let wrapper;

    beforeEach(async () => {
        global.activeFeatureFlags = [];
        wrapper = await createWrapper();
    });

    it('should get document types once component created', async () => {
        wrapper.vm.getDocumentTypes = jest.fn(() => Promise.resolve());

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.getDocumentTypes).toHaveBeenCalledTimes(1);
        wrapper.vm.getDocumentTypes.mockRestore();
    });

    it('should be able to get document types', async () => {
        const spy = jest.spyOn(wrapper.vm.documentTypeRepository, 'search').mockImplementation(() => {
            return Promise.resolve([
                {
                    id: 1,
                    technicalName: 'invoice',
                },
                {
                    id: 2,
                    technicalName: 'delivery_note',
                },
            ]);
        });

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.documentTypes).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    id: 1,
                    technicalName: 'invoice',
                    selected: false,
                }),
                expect.objectContaining({
                    id: 2,
                    technicalName: 'delivery_note',
                    selected: false,
                }),
            ]),
        );
        spy.mockRestore();
    });

    it('should label checkboxes with the translated document type name', async () => {
        const fixtures = Object.assign([...documentTypesFixtures], { total: documentTypesFixtures.length });
        jest.spyOn(wrapper.vm.documentTypeRepository, 'search').mockResolvedValueOnce(fixtures);

        await wrapper.vm.createdComponent();
        await flushPromises();

        const labels = wrapper.findAll('.mt-field__label label').map((label) => label.text());

        expect(labels).toEqual(documentTypesFixtures.map((type) => type.translated.name));
    });

    it('fetches document types from the available types endpoint', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        wrapper = await createWrapper();

        await wrapper.vm.createdComponent();
        await flushPromises();

        expect([...wrapper.vm.documentTypes]).toEqual([
            {
                id: 'invoice',
                technicalName: 'invoice',
                name: 'sw-order.components.createDocumentModal.documentTypes.invoice',
                selected: false,
            },
        ]);

        const labels = wrapper.findAll('.mt-field__label label').map((label) => label.text());
        expect(labels).toEqual(['sw-order.components.createDocumentModal.documentTypes.invoice']);
    });
});
