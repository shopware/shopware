/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

const {
    Data: { EntityCollection },
    Context,
} = Shopware;
const pinia = createPinia();

const documentTypesFixtures = [
    {
        id: 'invoice-id',
        technicalName: 'invoice',
        translated: { name: 'Invoice' },
    },
    {
        id: 'delivery-note-id',
        technicalName: 'delivery_note',
        translated: { name: 'Delivery note' },
    },
    {
        id: 'credit-note-id',
        technicalName: 'credit_note',
        translated: { name: 'Credit note' },
    },
];

const documentTypeRepositoryMock = {
    search: jest.fn(() =>
        Promise.resolve(
            new EntityCollection(
                '',
                'document_type',
                Context.api,
                null,
                documentTypesFixtures,
                documentTypesFixtures.length,
            ),
        ),
    ),
};

const repositoryFactoryMock = {
    create: (entity) => {
        if (entity === 'document_type') {
            return documentTypeRepositoryMock;
        }

        return null;
    },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-bulk-edit-order-documents-delete-documents', { sync: true }), {
        global: {
            stubs: {
                'sw-checkbox-field': true,
            },
            provide: {
                repositoryFactory: repositoryFactoryMock,
            },
        },
    });
}

describe('sw-bulk-edit-order-documents-delete-documents', () => {
    beforeEach(() => {
        setActivePinia(pinia);
    });

    it('should render checkboxes for each document type', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('.mt-field__checkbox')).toHaveLength(documentTypesFixtures.length);
    });

    it('should be able to select document types', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const checkboxes = wrapper.find('input');
        await checkboxes.setValue('checked');
        await flushPromises();

        expect(wrapper.find('input').element.checked).toBe(true);
    });

    it('should not render checkboxes when fetching document types fails', async () => {
        documentTypeRepositoryMock.search.mockRejectedValueOnce(new Error('failed to fetch document types'));
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('.mt-field__checkbox')).toHaveLength(0);
    });

    it('should render no checkboxes when no document types exists', async () => {
        documentTypeRepositoryMock.search.mockResolvedValueOnce(
            new EntityCollection('', 'document_type', Context.api, null, [], 0),
        );
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('.mt-field__checkbox')).toHaveLength(0);
    });
});
