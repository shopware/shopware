/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { ComponentPublicInstance } from 'vue';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import { createWrapper, createMedia, clickButton, selectMedia, getDocuments } from './helpers';

describe('sw-product-document-form translated editing', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    const originalLanguageMetadata = Shopware.Store.get('context').api.language;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.parentProduct = repositoryFactory.create('product').create();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        Shopware.Store.get('context').api.language = originalLanguageMetadata;
    });

    it.each(['title', 'file'])('preserves fallback of untouched fields when changing the %s in German', async (field) => {
        const repository = repositoryFactory.create('product');
        const productId = repository.create().id;
        const documentId = repositoryFactory.create('product_document').create().id;
        const germanLanguageId = repositoryFactory.create('language').create().id;
        const versionId = '0fa91ce3e96a4bc2be4bd9ce752c3425';
        const englishMedia = createMedia('english-manual');
        const germanMedia = createMedia('german-manual');
        const updatedEnglishMedia = createMedia('updated-english-manual');
        Shopware.Context.api.languageId = germanLanguageId;
        Shopware.Store.get('context').api.language = repositoryFactory
            .create('language')
            .create(Shopware.Context.api, Shopware.Context.api.languageId);
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

        async function loadProduct(
            englishTitle: string,
            defaultMedia: Entity<'media'>,
            germanTitle: string | null,
            selectedMedia: Entity<'media'> | null,
        ) {
            const translations = [
                { languageId: Shopware.Context.api.systemLanguageId, title: englishTitle, media: defaultMedia },
                { languageId: germanLanguageId, title: germanTitle, media: selectedMedia },
            ].map(({ languageId, title, media }) => ({
                id: `${documentId}-${languageId}`,
                type: 'product_document_translation',
                attributes: { extensions: {}, productDocumentId: documentId, languageId, title, mediaId: media?.id ?? null },
                relationships: { media: { data: media ? { type: 'media', id: media.id } : null } },
            }));
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
                                    data: [{ type: 'product_document', id: documentId }],
                                    links: {
                                        related: `${Shopware.Context.api.apiResourcePath}/product/${productId}/documents`,
                                    },
                                },
                            },
                        },
                    ],
                    included: [
                        {
                            id: documentId,
                            type: 'product_document',
                            attributes: {
                                extensions: {},
                                productId,
                                versionId,
                                position: 4,
                                title: germanTitle,
                                mediaId: selectedMedia?.id ?? null,
                                translated: {
                                    title: germanTitle ?? englishTitle,
                                    mediaId: selectedMedia?.id ?? defaultMedia.id,
                                },
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
                        ...[defaultMedia, ...(selectedMedia ? [selectedMedia] : [])].map((media) => ({
                            id: media.id,
                            type: 'media',
                            relationships: {},
                            attributes: {
                                extensions: {},
                                fileName: media.fileName,
                                fileExtension: media.fileExtension,
                                fileSize: media.fileSize,
                            },
                        })),
                    ],
                },
            });
            return (await repository.get(productId, Shopware.Context.api))!;
        }

        const store = Shopware.Store.get('swProductDetail');
        store.product = await loadProduct('Manual', englishMedia, null, null);
        const wrapper = await createWrapper(undefined, undefined, repository);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        if (field === 'title') {
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Handbuch');
            await flushPromises();
        } else {
            await selectMedia(wrapper, [germanMedia]);
        }
        await clickButton(wrapper, 'global.default.apply');
        responses.addResponse({ method: 'PATCH', url: `/product/${productId}`, status: 204, response: {} });
        const patchSpy = jest.spyOn(repository.httpClient, 'patch');

        await repository.save(store.product, Shopware.Context.api);

        const changedField = field === 'title' ? { title: 'Handbuch' } : { mediaId: germanMedia.id };
        expect(patchSpy.mock.calls[0][1]).toEqual({
            id: productId,
            versionId,
            documents: [{ id: documentId, versionId, ...changedField }],
        });
        expect(patchSpy.mock.calls[0][2]).toMatchObject({ headers: { 'sw-language-id': germanLanguageId } });
        const savedTitle = field === 'title' ? 'Handbuch' : null;
        const savedMedia = field === 'file' ? germanMedia : null;
        store.product = await loadProduct('Manual', englishMedia, savedTitle, savedMedia);
        await flushPromises();
        expect(getDocuments()![0]).toMatchObject({ title: savedTitle, mediaId: savedMedia?.id ?? null, position: 4 });
        expect(new ChangesetGenerator().generate(store.product).changes).toBeNull();
        expect(
            getDocuments()![0].translations.find(
                (translation) => translation.languageId === Shopware.Context.api.systemLanguageId,
            ),
        ).toMatchObject({ title: 'Manual', mediaId: englishMedia.id });

        // A later default-language update must still reach the untouched German field.
        store.product = await loadProduct('Updated manual', updatedEnglishMedia, savedTitle, savedMedia);
        await flushPromises();
        expect(wrapper.findAll('.document-row')[0].text()).toContain(field === 'title' ? 'Handbuch' : 'Updated manual');
        expect(wrapper.findAll('.document-row')[0].text()).toContain(
            field === 'file' ? 'german-manual.pdf' : 'updated-english-manual.pdf',
        );

        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Discard this name');
        if (field === 'file') {
            await wrapper.get('.sw-product-document-form__remove-file').trigger('click');
        }
        await selectMedia(wrapper, [createMedia('discard-this-file')]);
        await clickButton(wrapper, 'global.default.cancel');
        expect(new ChangesetGenerator().generate(store.product).changes).toBeNull();
    });
});
