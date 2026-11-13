/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import { withProductStates, cleanupWrappers, createWrapper, createMedia, getDocuments } from './helpers';

describe('sw-product-detail-base document save and reload', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        Shopware.Store.get('swProductDetail').$reset();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        cleanupWrappers();
    });

    it('saves independent translated assignments and deletes them when restoring inheritance after reload', async () => {
        const repository = repositoryFactory.create('product');
        const parentId = repository.create().id;
        const variantId = repository.create().id;
        const parentDocumentId = repositoryFactory.create('product_document').create().id;
        const media = createMedia('parent-manual');
        const translatedMedia = createMedia('handbuch');
        const germanLanguageId = repositoryFactory.create('language').create().id;
        const translations = [
            { languageId: Shopware.Context.api.systemLanguageId, title: 'Parent manual', mediaId: media.id },
            { languageId: germanLanguageId, title: 'Handbuch', mediaId: translatedMedia.id },
        ];

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

        // Mock only the HTTP boundary; repository changesets and response hydration stay real.
        async function loadProduct(
            productId: EntityKey<'product'>,
            parentProductId: EntityKey<'product'> | null,
            documentId: EntityKey<'product_document'> | null,
            title: string,
        ) {
            const translationRows = translations.map((translation) => ({
                id: `${documentId}-${translation.languageId}`,
                type: 'product_document_translation',
                attributes: {
                    extensions: {},
                    ...translation,
                    title: translation.languageId === Shopware.Context.api.systemLanguageId ? title : translation.title,
                    productDocumentId: documentId,
                },
                relationships: {
                    media: { data: { type: 'media', id: translation.mediaId } },
                },
            }));
            const document = {
                id: documentId,
                type: 'product_document',
                attributes: { extensions: {}, productId, mediaId: media.id, title, position: 0 },
                relationships: {
                    translations: {
                        data: translationRows.map(({ id: translationId, type }) => ({ id: translationId, type })),
                        links: {
                            related: `${Shopware.Context.api.apiResourcePath}/product-document/${documentId}/translations`,
                        },
                    },
                },
            };
            responses.addResponse({
                method: 'POST',
                url: '/search/product',
                response: {
                    data: [
                        {
                            id: productId,
                            type: 'product',
                            attributes: { parentId: parentProductId, extensions: {} },
                            relationships: {
                                documents: {
                                    data: documentId ? [{ type: 'product_document', id: documentId }] : [],
                                    links: {
                                        related: `${Shopware.Context.api.apiResourcePath}/product/${productId}/documents`,
                                    },
                                },
                            },
                        },
                    ],
                    included: documentId
                        ? [
                              document,
                              ...translationRows,
                              ...[media, translatedMedia].map((file) => ({
                                  id: file.id,
                                  type: 'media',
                                  attributes: {
                                      extensions: {},
                                      fileName: file.fileName,
                                      fileExtension: file.fileExtension,
                                      fileSize: file.fileSize,
                                  },
                                  relationships: {},
                              })),
                          ]
                        : [],
                },
            });
            return withProductStates((await repository.get(productId, Shopware.Context.api))!);
        }

        const store = Shopware.Store.get('swProductDetail');
        store.parentProduct = await loadProduct(parentId, null, parentDocumentId, 'Parent manual');
        store.product = await loadProduct(variantId, parentId, null, '');
        const parentDocument = store.parentProduct.documents![0];
        const parentOrigin = JSON.stringify(parentDocument.getOrigin());
        const wrapper = await createWrapper(undefined, undefined, repository, { baseView: true });

        await wrapper
            .get('.sw-product-detail-base__documents .sw-inheritance-switch--is-inherited .mt-icon')
            .trigger('click');
        const documentId = getDocuments()![0].id;
        getDocuments()![0].title = 'Variant manual';
        expect(documentId).not.toBe(parentDocumentId);
        responses.addResponse({
            method: 'PATCH',
            url: `/product/${variantId}`,
            status: 204,
            response: {},
        });
        const patchSpy = jest.spyOn(repository.httpClient, 'patch');

        await repository.save(store.product, Shopware.Context.api);

        expect(patchSpy).toHaveBeenCalledTimes(1);
        expect(patchSpy.mock.calls[0][0]).toBe(`/product/${variantId}`);
        expect(patchSpy.mock.calls[0][1]).toMatchObject({
            documents: [
                { id: documentId, productId: variantId, title: 'Variant manual', translations },
            ],
        });

        store.product = await loadProduct(variantId, parentId, documentId, 'Variant manual');
        await flushPromises();
        expect(wrapper.text()).toContain('Variant manual');
        expect(wrapper.text()).toContain('parent-manual.pdf');
        expect(getDocuments()![0].translations).toHaveLength(2);
        expect(new ChangesetGenerator().generate(store.product).changes).toBeNull();

        await wrapper
            .get('.sw-product-detail-base__documents .sw-inheritance-switch--is-not-inherited .mt-icon')
            .trigger('click');
        const deleteRoute = `/product/${variantId}/documents/${documentId}`;
        responses.addResponse({
            method: 'DELETE',
            url: deleteRoute,
            status: 204,
            response: {},
        });
        const deleteSpy = jest.spyOn(repository.httpClient, 'delete');

        await repository.save(store.product, Shopware.Context.api);

        expect(deleteSpy).toHaveBeenCalledTimes(1);
        expect(deleteSpy).toHaveBeenCalledWith(deleteRoute, expect.anything());
        store.product = await loadProduct(variantId, parentId, null, '');
        await flushPromises();
        expect(getDocuments()).toHaveLength(0);
        expect(wrapper.text()).toContain('Parent manual');
        expect(wrapper.find('.sw-inheritance-switch--is-inherited').exists()).toBe(true);
        expect(JSON.stringify(parentDocument.getOrigin())).toBe(parentOrigin);
        expect(parentDocument.title).toBe('Parent manual');
    });
});
