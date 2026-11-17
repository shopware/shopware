/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import {
    withProductStates,
    createProduct,
    cleanupWrappers,
    createWrapper,
    createMedia,
    addMedia,
    clickButton,
    getDocuments,
} from './helpers';

describe('sw-product-document-form removal', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        cleanupWrappers();
    });

    it('cancels removal without changing the product draft', async () => {
        const wrapper = await createWrapper();
        await addMedia(wrapper, [createMedia()]);
        const before = new ChangesetGenerator().generate(Shopware.Store.get('swProductDetail').product);
        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        expect(wrapper.text()).toContain('sw-product.documentForm.removeDescription');
        await clickButton(wrapper, 'global.default.cancel');
        expect(getDocuments()).toHaveLength(1);
        expect(new ChangesetGenerator().generate(Shopware.Store.get('swProductDetail').product)).toEqual(before);
    });

    it('retries a failed Sync save, reloads the removal, and keeps media from every translation', async () => {
        const repository = repositoryFactory.create('product', '', { useSync: true });
        const documentRepository = repositoryFactory.create('product_document');
        const mediaRepository = repositoryFactory.create('media');
        const productId = repository.create().id;
        const documentId = documentRepository.create().id;
        const remainingId = documentRepository.create().id;
        const versionId = '0fa91ce3e96a4bc2be4bd9ce752c3425';
        const germanLanguageId = repositoryFactory.create('language').create().id;
        const media = createMedia();
        const germanMedia = createMedia('handbuch');
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
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
        const files = [media, germanMedia].map((file) => ({
            id: file.id,
            type: 'media',
            relationships: {},
            attributes: {
                extensions: {},
                fileName: file.fileName,
                fileExtension: file.fileExtension,
                fileSize: file.fileSize,
            },
        }));

        async function loadProduct(documentIds: EntityKey<'product_document'>[]) {
            const documents = documentIds.map((id, position) => {
                const translations = [
                    { languageId: Shopware.Context.api.systemLanguageId, title: 'Manual', mediaId: media.id },
                    { languageId: germanLanguageId, title: 'Handbuch', mediaId: germanMedia.id },
                ].map((translation) => ({
                    id: `${id}-${translation.languageId}`,
                    type: 'product_document_translation',
                    attributes: { extensions: {}, productDocumentId: id, ...translation },
                    relationships: { media: { data: { type: 'media', id: translation.mediaId } } },
                }));
                return {
                    document: {
                        id,
                        type: 'product_document',
                        attributes: {
                            extensions: {},
                            productId,
                            versionId,
                            position,
                            title: 'Manual',
                            mediaId: media.id,
                        },
                        relationships: {
                            translations: {
                                data: translations.map((translation) => ({ type: translation.type, id: translation.id })),
                                links: {
                                    related: `${Shopware.Context.api.apiResourcePath}/product-document/${id}/translations`,
                                },
                            },
                        },
                    },
                    translations,
                };
            });
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
                                    data: documentIds.map((id) => ({ type: 'product_document', id })),
                                    links: {
                                        related: `${Shopware.Context.api.apiResourcePath}/product/${productId}/documents`,
                                    },
                                },
                            },
                        },
                    ],
                    included: [...documents.flatMap((item) => [item.document, ...item.translations]), ...files],
                },
            });
            return withProductStates((await repository.get(productId, Shopware.Context.api))!);
        }

        const store = Shopware.Store.get('swProductDetail');
        store.product = await loadProduct([documentId, remainingId]);
        const wrapper = await createWrapper(undefined, undefined, repository);
        const syncSpy = jest.spyOn(repository.httpClient, 'post');
        const deleteSpy = jest.spyOn(repository.httpClient, 'delete');
        const firstRow = wrapper.findAll('.document-row')[0];
        await firstRow.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        await clickButton(wrapper, 'global.default.cancel');
        expect(getDocuments()).toHaveLength(2);
        expect(getDocuments()![0].translations).toHaveLength(2);
        expect(new ChangesetGenerator().generate(store.product)).toEqual({ changes: null, deletionQueue: [] });
        await firstRow.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        await clickButton(wrapper, 'global.default.remove');
        expect(syncSpy).not.toHaveBeenCalled();
        expect(deleteSpy).not.toHaveBeenCalled();

        responses.addResponse({ method: 'POST', url: '_action/sync', status: 400, response: { errors: [] } });
        await expect(repository.save(store.product, Shopware.Context.api)).rejects.toMatchObject({
            response: { status: 400 },
        });
        expect(Array.from(getDocuments()!, (document) => document.id)).toEqual([remainingId]);
        expect(new ChangesetGenerator().generate(store.product).deletionQueue).toHaveLength(1);

        responses.addResponse({ method: 'POST', url: '_action/sync', status: 200, response: {} });
        await repository.save(store.product, Shopware.Context.api);

        const operations = [
            { action: 'delete', entity: 'product_document', payload: [{ id: documentId }] },
            {
                key: 'write',
                action: 'upsert',
                entity: 'product',
                payload: [
                    {
                        id: productId,
                        versionId,
                        documents: [{ id: remainingId, versionId, position: 0 }],
                    },
                ],
            },
        ];
        expect(syncSpy).toHaveBeenCalledTimes(2);
        expect(syncSpy.mock.calls[0][0]).toBe('_action/sync');
        expect(syncSpy.mock.calls[0][1]).toEqual(operations);
        expect(syncSpy.mock.calls[1][1]).toEqual(operations);
        expect(syncSpy.mock.calls[1][2]).toMatchObject({
            headers: { 'single-operation': true, 'sw-language-id': Shopware.Context.api.systemLanguageId },
        });
        expect(deleteSpy).not.toHaveBeenCalled();

        store.product = await loadProduct([remainingId]);
        await flushPromises();
        expect(Array.from(getDocuments()!, (document) => document.id)).toEqual([remainingId]);
        expect(getDocuments()![0].position).toBe(0);
        expect(new ChangesetGenerator().generate(store.product).changes).toBeNull();
        responses.addResponse({ method: 'POST', url: '/search/media', response: { data: files } });
        expect(await mediaRepository.get(media.id, Shopware.Context.api)).toMatchObject({
            id: media.id,
            fileName: 'manual',
        });
        expect(await mediaRepository.get(germanMedia.id, Shopware.Context.api)).toMatchObject({
            id: germanMedia.id,
            fileName: 'handbuch',
        });
    });
});
