/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';

import { createWrapper, createMedia, addMedia, getDocuments } from './helpers';

describe('sw-product-document-form ordering', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        Object.assign(store, { product: repositoryFactory.create('product').create() });
        store.parentProduct = repositoryFactory.create('product').create();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
    });

    it('moves documents up and down and disables movement beyond the list boundaries', async () => {
        const wrapper = await createWrapper();
        await addMedia(wrapper, [createMedia('first'), createMedia('second')]);
        await wrapper.findAll('.document-row')[1].findAll('button')[1].trigger('click');

        expect(Array.from(getDocuments() ?? [], (document) => document.title)).toEqual(['second', 'first']);
        expect(Array.from(getDocuments() ?? [], (document) => document.position)).toEqual([0, 1]);
        expect(wrapper.findAll('.document-row')[0].text()).toContain('second');
        expect(wrapper.findAll('.document-row')[0].findAll('button')[1].attributes('disabled')).toBeDefined();
        expect(wrapper.findAll('.document-row')[1].findAll('button')[0].attributes('disabled')).toBeDefined();
        await wrapper.findAll('.document-row')[0].findAll('button')[0].trigger('click');
        expect(Array.from(getDocuments() ?? [], (document) => document.title)).toEqual(['first', 'second']);
        expect(Array.from(getDocuments() ?? [], (document) => document.position)).toEqual([0, 1]);
    });

    it.each(['viewer', 'inherited'])('prevents reordering when access is %s', async (scenario) => {
        const store = Shopware.Store.get('swProductDetail');
        const isInherited = scenario === 'inherited';
        if (scenario === 'viewer') {
            Object.assign(global, { activeAclRoles: ['product.viewer'] });
        }
        const documents = isInherited ? store.parentProduct.documents! : store.product.documents!;
        ['First manual', 'Second manual'].forEach((title, position) => {
            const document = repositoryFactory.create('product_document').create();
            Object.assign(document, { title, position });
            documents.add(document);
        });
        const originalIds = Array.from(documents, (document) => document.id);
        const wrapper = await createWrapper(undefined, undefined, undefined, { isInherited });
        const moveUpButton = wrapper.findAll('.document-row')[1].findAll('button')[1];

        expect(moveUpButton.attributes('disabled')).toBeDefined();
        await moveUpButton.trigger('click');

        expect(Array.from(documents, (document) => document.id)).toEqual(originalIds);
        expect(Array.from(documents, (document) => document.position)).toEqual([0, 1]);
    });

    it('saves changed positions of existing documents and reloads them in the saved order', async () => {
        const repository = repositoryFactory.create('product');
        const documentRepository = repositoryFactory.create('product_document');
        const productId = repository.create().id;
        const versionId = '0fa91ce3e96a4bc2be4bd9ce752c3425';
        const first = { id: documentRepository.create().id, title: 'First manual' };
        const second = { id: documentRepository.create().id, title: 'Second manual' };
        const third = { id: documentRepository.create().id, title: 'Third manual' };
        const { repositoryFactoryMock } = global as typeof globalThis & {
            repositoryFactoryMock: {
                responses: {
                    addResponse(response: {
                        method: string;
                        url: string;
                        status?: number;
                        response: Record<string, unknown>;
                    }): void;
                };
            };
        };
        const { responses } = repositoryFactoryMock;

        async function loadProduct(documents: { id: EntityKey<'product_document'>; title: string }[]) {
            responses.addResponse({
                method: 'POST',
                url: '/search/product',
                response: {
                    data: [
                        {
                            id: productId,
                            type: 'product',
                            attributes: { extensions: {}, versionId },
                            relationships: {
                                documents: {
                                    data: documents.map(({ id }) => ({ type: 'product_document', id })),
                                    links: {
                                        related: `${Shopware.Context.api.apiResourcePath}/product/${productId}/documents`,
                                    },
                                },
                            },
                        },
                    ],
                    included: documents.map((document, position) => ({
                        id: document.id,
                        type: 'product_document',
                        attributes: { extensions: {}, productId, versionId, title: document.title, position },
                        relationships: {},
                    })),
                },
            });
            return (await repository.get(productId, Shopware.Context.api))!;
        }

        const store = Shopware.Store.get('swProductDetail');
        store.product = await loadProduct([first, second, third]);
        const wrapper = await createWrapper(undefined, undefined, repository);
        await wrapper.findAll('.document-row')[1].findAll('button')[1].trigger('click');
        responses.addResponse({ method: 'PATCH', url: `/product/${productId}`, status: 204, response: {} });
        const patchSpy = jest.spyOn(repository.httpClient, 'patch');

        await repository.save(store.product, Shopware.Context.api);

        expect(patchSpy).toHaveBeenCalledTimes(1);
        expect(patchSpy.mock.calls[0][0]).toBe(`/product/${productId}`);
        expect(patchSpy.mock.calls[0][1]).toEqual({
            id: productId,
            versionId,
            documents: [
                { id: second.id, versionId, position: 0 },
                { id: first.id, versionId, position: 1 },
            ],
        });

        store.product = await loadProduct([second, first, third]);
        await flushPromises();
        expect(wrapper.findAll('.document-row').map((row) => row.text())).toEqual([
            expect.stringContaining('Second manual'),
            expect.stringContaining('First manual'),
            expect.stringContaining('Third manual'),
        ]);
        expect(Array.from(getDocuments() ?? [], (document) => document.position)).toEqual([0, 1, 2]);
        expect(new ChangesetGenerator().generate(store.product).changes).toBeNull();
    });
});
