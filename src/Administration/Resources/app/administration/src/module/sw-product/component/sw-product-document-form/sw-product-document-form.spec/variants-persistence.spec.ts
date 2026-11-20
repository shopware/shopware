/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import type { ComponentPublicInstance } from 'vue';
import {
    mockVariantSync,
    withProductStates,
    createProduct,
    cleanupWrappers,
    createWrapper,
    clickButton,
    selectMedia,
    createMedia,
    getDocuments,
} from './helpers';

describe('sw-product-document-form variant persistence', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    const originalLanguageMetadata = Shopware.Store.get('context').api.language;
    const versionId = '0fa91ce3e96a4bc2be4bd9ce752c3425';
    const englishMediaId = repositoryFactory.create('media').create().id;
    const germanMediaId = repositoryFactory.create('media').create().id;
    const germanLanguageId = repositoryFactory.create('language').create().id;

    beforeEach(() => {
        Shopware.Context.api.languageId = germanLanguageId;
        Shopware.Store.get('context').api.language = repositoryFactory
            .create('language')
            .create(Shopware.Context.api, Shopware.Context.api.languageId);
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.parentProduct = createProduct();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        Shopware.Store.get('context').api.language = originalLanguageMetadata;
        cleanupWrappers();
        jest.restoreAllMocks();
    });

    function getResponses() {
        return (
            global as typeof globalThis & {
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
            }
        ).repositoryFactoryMock.responses;
    }

    async function loadProduct(
        productId: EntityKey<'product'>,
        documentId: EntityKey<'product_document'> | null,
        title: string | null = null,
        mediaId: string | null = null,
        defaultTitle = 'Manual',
        defaultMediaId = englishMediaId,
    ) {
        const responses = getResponses();
        const translations = [
            { languageId: Shopware.Context.api.systemLanguageId, title: defaultTitle, mediaId: defaultMediaId },
            { languageId: germanLanguageId, title, mediaId },
        ].map((translation) => ({
            id: `${documentId}-${translation.languageId}`,
            type: 'product_document_translation',
            attributes: { extensions: {}, productDocumentId: documentId, ...translation },
            relationships: { media: { data: translation.mediaId ? { type: 'media', id: translation.mediaId } : null } },
        }));
        responses.addResponse({
            method: 'POST',
            url: '/search/product',
            response: {
                data: [
                    {
                        id: productId,
                        type: 'product',
                        attributes: { extensions: {}, versionId, childCount: 1 },
                        relationships: {
                            documents: {
                                data: documentId ? [{ type: 'product_document', id: documentId }] : [],
                                links: { related: `${Shopware.Context.api.apiResourcePath}/product/${productId}/documents` },
                            },
                        },
                    },
                ],
                included: documentId
                    ? [
                          {
                              id: documentId,
                              type: 'product_document',
                              attributes: {
                                  extensions: {},
                                  productId,
                                  versionId,
                                  position: 0,
                                  title,
                                  mediaId,
                                  translated: { title: title ?? defaultTitle, mediaId: mediaId ?? defaultMediaId },
                              },
                              relationships: {
                                  translations: {
                                      data: translations.map(({ id, type }) => ({ id, type })),
                                      links: {
                                          related: `${Shopware.Context.api.apiResourcePath}/product-document/${documentId}/translations`,
                                      },
                                  },
                              },
                          },
                          ...translations,
                          ...Array.from(new Set([defaultMediaId, ...(mediaId ? [mediaId] : [])]), (id) => ({
                              id,
                              type: 'media',
                              relationships: {},
                              attributes: {
                                  extensions: {},
                                  fileName: 'manual',
                                  fileExtension: 'pdf',
                                  fileSize: 1024,
                                  private: false,
                              },
                          })),
                      ]
                    : [],
            },
        });
        return withProductStates((await repositoryFactory.create('product').get(productId, Shopware.Context.api))!);
    }

    it.each(['title', 'file'])(
        'saves a German %s edit on the parent only and queues the variant update while preserving the other fallback',
        async (field) => {
            const repository = repositoryFactory.create('product', '', { useSync: true });
            const productId = createProduct().id;
            const documentId = repositoryFactory.create('product_document').create().id;
            const store = Shopware.Store.get('swProductDetail');
            store.product = await loadProduct(productId, documentId);
            const title = field === 'title' ? 'Handbuch' : 'Manual';
            const mediaId = field === 'file' ? germanMediaId : englishMediaId;

            mockVariantSync.mockResolvedValueOnce(['variant-a']);
            const wrapper = await createWrapper(undefined, undefined, repository);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            if (field === 'title') {
                wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', title);
            } else {
                const replacement = createMedia('german-manual');
                replacement.id = germanMediaId;
                await selectMedia(wrapper, [replacement]);
            }
            wrapper
                .getComponent<ComponentPublicInstance>('.sw-product-document-form__apply-to-variants')
                .vm.$emit('update:checked', true);
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();
            const changedField = field === 'title' ? { title } : { mediaId };
            const operation = { type: 'update', mediaId: englishMediaId, title: 'Manual', changes: changedField };
            expect(mockVariantSync).toHaveBeenCalledTimes(1);
            expect(mockVariantSync).toHaveBeenCalledWith(productId, [operation], true);
            expect(store.documentVariantOperations).toEqual({ [documentId]: operation });

            const beforeSave = new ChangesetGenerator().generate(store.product);
            const responses = getResponses();
            const syncSpy = jest.spyOn(repository.httpClient, 'post');
            responses.addResponse({ method: 'POST', url: '_action/sync', status: 400, response: { errors: [] } });
            await expect(repository.save(store.product, Shopware.Context.api)).rejects.toMatchObject({
                response: { status: 400 },
            });
            expect(new ChangesetGenerator().generate(store.product)).toEqual(beforeSave);
            responses.addResponse({ method: 'POST', url: '_action/sync', status: 200, response: {} });
            await repository.save(store.product, Shopware.Context.api);
            const operations = [
                {
                    key: 'write',
                    action: 'upsert',
                    entity: 'product',
                    payload: [
                        {
                            id: productId,
                            versionId,
                            documents: [{ id: documentId, versionId, ...changedField, translated: { title, mediaId } }],
                        },
                    ],
                },
            ];
            expect(syncSpy).toHaveBeenCalledTimes(2);
            expect(syncSpy.mock.calls[0][1]).toEqual(operations);
            expect(syncSpy.mock.calls[1][1]).toEqual(operations);
            expect(syncSpy.mock.calls[1][2]).toMatchObject({
                headers: { 'single-operation': true, 'sw-language-id': germanLanguageId },
            });
            expect(mockVariantSync).toHaveBeenCalledTimes(1);

            store.product = await loadProduct(
                productId,
                documentId,
                field === 'title' ? title : null,
                field === 'file' ? mediaId : null,
            );
            await flushPromises();
            expect(getDocuments()![0]).toMatchObject({
                title: field === 'title' ? title : null,
                mediaId: field === 'file' ? mediaId : null,
                translated: { title, mediaId },
            });
            expect(wrapper.text()).toContain(title);
            expect(new ChangesetGenerator().generate(store.product).changes).toBeNull();
        },
    );

    it('deletes only the parent document in the Sync save and queues the variant removal', async () => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const repository = repositoryFactory.create('product', '', { useSync: true });
        const productId = createProduct().id;
        const documentId = repositoryFactory.create('product_document').create().id;
        const store = Shopware.Store.get('swProductDetail');
        store.product = await loadProduct(productId, documentId, 'Manual', englishMediaId);

        mockVariantSync.mockResolvedValueOnce(['variant-a']);
        const wrapper = await createWrapper(undefined, undefined, repository);
        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();
        const operation = { type: 'remove', mediaId: englishMediaId, title: 'Manual' };
        expect(mockVariantSync).toHaveBeenCalledWith(productId, [operation], true);
        expect(store.documentVariantOperations).toEqual({ [documentId]: operation });

        const responses = getResponses();
        responses.addResponse({ method: 'POST', url: '_action/sync', status: 200, response: {} });
        const syncSpy = jest.spyOn(repository.httpClient, 'post');
        const deleteSpy = jest.spyOn(repository.httpClient, 'delete');
        await repository.save(store.product, Shopware.Context.api);
        expect(syncSpy).toHaveBeenCalledTimes(1);
        expect(syncSpy.mock.calls[0][1]).toEqual([
            { action: 'delete', entity: 'product_document', payload: [{ id: documentId }] },
        ]);
        expect(deleteSpy).not.toHaveBeenCalled();
        expect(mockVariantSync).toHaveBeenCalledTimes(1);
        store.product = await loadProduct(productId, null);
        expect(store.product.documents).toHaveLength(0);
        expect(new ChangesetGenerator().generate(store.product)).toEqual({ changes: null, deletionQueue: [] });
    });
});
