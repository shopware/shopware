/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import type { ComponentPublicInstance } from 'vue';
import { createWrapper, clickButton, selectMedia, createMedia } from './helpers';

describe('sw-product-document-form variant persistence', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    const versionId = '0fa91ce3e96a4bc2be4bd9ce752c3425';
    const englishMediaId = repositoryFactory.create('media').create().id;
    const germanMediaId = repositoryFactory.create('media').create().id;
    const germanLanguageId = repositoryFactory.create('language').create().id;

    beforeEach(() => {
        Shopware.Context.api.languageId = germanLanguageId;
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.parentProduct = repositoryFactory.create('product').create();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
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
        return (await repositoryFactory.create('product').get(productId, Shopware.Context.api))!;
    }

    it.each(['title', 'file'])('saves and reloads a German %s edit without pinning the other fallback', async (field) => {
        const repository = repositoryFactory.create('product', '', { useSync: true });
        const productId = repositoryFactory.create('product').create().id;
        const variantId = repositoryFactory.create('product').create().id;
        const documentId = repositoryFactory.create('product_document').create().id;
        const variantDocumentId = repositoryFactory.create('product_document').create().id;
        const store = Shopware.Store.get('swProductDetail');
        store.product = await loadProduct(productId, documentId);
        const variant = await loadProduct(variantId, variantDocumentId);
        const title = field === 'title' ? 'Handbuch' : 'Manual';
        const mediaId = field === 'file' ? germanMediaId : englishMediaId;

        jest.spyOn(repository, 'search').mockResolvedValueOnce(
            new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [variant]),
        );
        const wrapper = await createWrapper(undefined, undefined, repository);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        if (field === 'title') {
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', title);
        } else {
            await wrapper.get('.sw-product-document-form__remove-file').trigger('click');
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
        const beforeSave = new ChangesetGenerator().generate(store.product);
        const responses = getResponses();
        const syncSpy = jest.spyOn(repository.httpClient, 'post');
        responses.addResponse({ method: 'POST', url: '_action/sync', status: 400, response: { errors: [] } });
        await expect(repository.save(store.product, Shopware.Context.api)).rejects.toMatchObject({
            response: { status: 400 },
        });
        expect(new ChangesetGenerator().generate(store.product)).toEqual(beforeSave);
        expect(store.documentSaveNotifications).toHaveLength(1);
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
                        documents: [{ id: documentId, versionId, ...changedField }],
                        children: [
                            { id: variantId, versionId, documents: [{ id: variantDocumentId, versionId, ...changedField }] },
                        ],
                    },
                ],
            },
        ];
        expect(syncSpy.mock.calls[0][1]).toEqual(operations);
        expect(syncSpy.mock.calls[1][1]).toEqual(operations);
        expect(syncSpy.mock.calls[1][2]).toMatchObject({
            headers: { 'single-operation': true, 'sw-language-id': germanLanguageId },
        });
        store.product = await loadProduct(
            productId,
            documentId,
            field === 'title' ? title : null,
            field === 'file' ? mediaId : null,
        );
        const reloaded = await loadProduct(
            variantId,
            variantDocumentId,
            field === 'title' ? title : null,
            field === 'file' ? mediaId : null,
            'Updated manual',
            germanMediaId,
        );
        expect(reloaded.documents![0]).toMatchObject({
            title: field === 'title' ? title : null,
            mediaId: field === 'file' ? mediaId : null,
            translated: { title: field === 'title' ? title : 'Updated manual', mediaId: germanMediaId },
        });
        expect(
            reloaded.documents![0].translations.find(
                (translation) => translation.languageId === Shopware.Context.api.systemLanguageId,
            ),
        ).toMatchObject({ title: 'Updated manual', mediaId: germanMediaId });
        expect(new ChangesetGenerator().generate(reloaded).changes).toBeNull();
        expect(new ChangesetGenerator().generate(store.product).changes).toBeNull();
    });

    it('includes loaded variant deletions in the parent Sync save and reloads both removals', async () => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const repository = repositoryFactory.create('product', '', { useSync: true });
        const productId = repositoryFactory.create('product').create().id;
        const variantId = repositoryFactory.create('product').create().id;
        const documentId = repositoryFactory.create('product_document').create().id;
        const variantDocumentId = repositoryFactory.create('product_document').create().id;
        const store = Shopware.Store.get('swProductDetail');
        store.product = await loadProduct(productId, documentId, 'Manual', englishMediaId);
        const variant = await loadProduct(variantId, variantDocumentId, 'Manual', englishMediaId);

        jest.spyOn(repository, 'search').mockResolvedValueOnce(
            new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [variant]),
        );
        const wrapper = await createWrapper(undefined, undefined, repository);
        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();
        const responses = getResponses();
        responses.addResponse({ method: 'POST', url: '_action/sync', status: 200, response: {} });
        const syncSpy = jest.spyOn(repository.httpClient, 'post');
        const deleteSpy = jest.spyOn(repository.httpClient, 'delete');
        await repository.save(store.product, Shopware.Context.api);
        expect(syncSpy.mock.calls[0][1]).toContainEqual({
            action: 'delete',
            entity: 'product_document',
            payload: [{ id: documentId }, { id: variantDocumentId }],
        });
        expect(syncSpy.mock.calls[0][1]).not.toEqual(expect.arrayContaining([expect.objectContaining({ entity: 'media' })]));
        expect(deleteSpy).not.toHaveBeenCalled();
        store.product = await loadProduct(productId, null);
        expect(store.product.documents).toHaveLength(0);
        expect((await loadProduct(variantId, null)).documents).toHaveLength(0);
        expect(new ChangesetGenerator().generate(store.product)).toEqual({ changes: null, deletionQueue: [] });
    });
});
