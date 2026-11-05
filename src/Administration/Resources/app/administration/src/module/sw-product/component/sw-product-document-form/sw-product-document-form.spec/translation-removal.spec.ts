/**
 * @sw-package inventory
 */
import type { VueWrapper } from '@vue/test-utils';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import { createWrapper, createMedia, clickButton, selectMedia } from './helpers';

describe('sw-product-document-form language-version removal', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    const originalLanguage = Shopware.Store.get('context').api.language;
    const repository = repositoryFactory.create('product');
    const germanLanguageId = repositoryFactory.create('language').create().id;
    const englishMedia = createMedia('english-manual');
    const germanMedia = createMedia('german-manual');
    const versionId = '0fa91ce3e96a4bc2be4bd9ce752c3425';
    let wrapper: VueWrapper;

    async function loadProduct(
        title: string | null,
        mediaId: string | null,
        id = repository.create().id,
        parentId: string | null = null,
    ) {
        const documentId = id;
        const { repositoryFactoryMock } = global as typeof globalThis & {
            repositoryFactoryMock: { responses: { addResponse(response: object): void } };
        };
        const translations = [
            { languageId: Shopware.Context.api.systemLanguageId, title: 'Manual', mediaId: englishMedia.id },
            { languageId: germanLanguageId, title, mediaId },
        ].map((translation) => ({
            id: `${documentId}-${translation.languageId}`,
            type: 'product_document_translation',
            attributes: { extensions: {}, productDocumentId: documentId, ...translation },
            relationships: { media: { data: translation.mediaId ? { type: 'media', id: translation.mediaId } : null } },
        }));
        repositoryFactoryMock.responses.addResponse({
            method: 'POST',
            url: '/search/product',
            response: {
                data: [
                    {
                        id,
                        type: 'product',
                        attributes: { extensions: {}, versionId, parentId },
                        relationships: {
                            documents: {
                                data: [{ id: documentId, type: 'product_document' }],
                                links: { related: `/product/${id}/documents` },
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
                            versionId,
                            productId: id,
                            position: 4,
                            title,
                            mediaId,
                            customFields: { note: 'Keep this' },
                            translated: { title: title ?? 'Manual', mediaId: mediaId ?? englishMedia.id },
                        },
                        relationships: {
                            translations: {
                                data: translations.map((translation) => ({ id: translation.id, type: translation.type })),
                                links: { related: `/product-document/${documentId}/translations` },
                            },
                        },
                    },
                    ...translations,
                    ...[englishMedia, germanMedia].map((media) => ({
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
        return (await repository.get(id, Shopware.Context.api))!;
    }

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor', 'media.viewer'] });
        Shopware.Context.api.languageId = germanLanguageId;
        Object.assign(Shopware.Store.get('context').api, { language: { name: 'Deutsch', locale: { code: 'de-DE' } } });
        Shopware.Store.get('swProductDetail').$reset();
        Shopware.Store.get('swProductDetail').parentProduct = repository.create();
    });

    afterEach(() => {
        wrapper?.unmount();
        Shopware.Context.api.languageId = originalLanguageId;
        Shopware.Store.get('context').api.language = originalLanguage;
        jest.restoreAllMocks();
    });

    it.each([
        ['name', 'Handbuch', null],
        ['file', null, germanMedia.id],
        ['name and file', 'Handbuch', germanMedia.id],
    ])('resets an overridden %s only on confirmation, without deleting the document', async (_field, title, mediaId) => {
        const store = Shopware.Store.get('swProductDetail');
        store.product = await loadProduct(title, mediaId);
        wrapper = await createWrapper(undefined, undefined, repository);
        const document = store.product.documents![0];
        const before = new ChangesetGenerator().generate(store.product);
        expect(wrapper.findAll('sw-context-menu-item-stub').at(-1)!.text()).toBe(
            'sw-product.documentForm.removeLanguageVersion',
        );
        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        expect(wrapper.text()).toContain('sw-product.documentForm.removeLanguageVersionDescription');
        await clickButton(wrapper, 'global.default.cancel');
        expect(new ChangesetGenerator().generate(store.product)).toEqual(before);

        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        await clickButton(wrapper, 'global.default.remove');
        expect(store.product.documents).toHaveLength(1);
        expect(document).toMatchObject({ title: null, mediaId: null, position: 4, customFields: { note: 'Keep this' } });
        expect(wrapper.find('.document-row').text()).toContain('Manual');
        expect(wrapper.find('.document-row').text()).toContain('english-manual.pdf');
        expect(wrapper.find('.mt-badge').exists()).toBe(false);
        expect(wrapper.findAll('sw-context-menu-item-stub')).toHaveLength(2);
        expect(new ChangesetGenerator().generate(store.product).deletionQueue).toEqual([]);
        expect(
            document.translations.find((translation) => translation.languageId === Shopware.Context.api.systemLanguageId),
        ).toMatchObject({ title: 'Manual', mediaId: englishMedia.id });

        const patchSpy = jest.spyOn(repository.httpClient, 'patch').mockResolvedValue({ data: {} });
        await repository.save(store.product, Shopware.Context.api);
        expect(patchSpy.mock.calls[0][1]).toMatchObject({
            documents: [{ id: document.id, ...(title ? { title: null } : {}), ...(mediaId ? { mediaId: null } : {}) }],
        });
        expect(patchSpy.mock.calls[0][2]).toMatchObject({ headers: { 'sw-language-id': germanLanguageId } });
        store.product = await loadProduct(null, null, store.product.id);
        await flushPromises();
        expect(wrapper.find('.document-row').text()).toContain('english-manual.pdf');
        expect(new ChangesetGenerator().generate(store.product)).toEqual({ changes: null, deletionQueue: [] });
    });

    it.each([
        ['name', 'Handbuch', null],
        ['file', null, germanMedia.id],
        ['name and file', 'Handbuch', germanMedia.id],
    ])('keeps the saved %s override when removal is undone before saving', async (_field, title, mediaId) => {
        const store = Shopware.Store.get('swProductDetail');
        store.product = await loadProduct(title, mediaId);
        wrapper = await createWrapper(undefined, undefined, repository);
        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        await clickButton(wrapper, 'global.default.remove');
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        if (title) {
            await wrapper.get('.sw-product-document-form__name-input input').setValue(title);
        }
        if (mediaId) {
            await selectMedia(wrapper, [germanMedia]);
        }
        await clickButton(wrapper, 'global.default.apply');
        const document = store.product.documents![0];
        expect(document).toMatchObject({ title, mediaId });
        expect(document.translations.find((translation) => translation.languageId === germanLanguageId)).toMatchObject({
            title,
            mediaId,
        });

        const patchSpy = jest.spyOn(repository.httpClient, 'patch').mockResolvedValue({ data: {} });
        await repository.save(store.product, Shopware.Context.api);
        const requests = JSON.stringify(patchSpy.mock.calls);
        expect(requests).not.toContain('"translations"');
        expect(requests).not.toContain('"title":null');
        expect(requests).not.toContain('"mediaId":null');
    });

    it('saves the language removal after unlinking inherited documents, preserving other languages', async () => {
        const store = Shopware.Store.get('swProductDetail');
        store.parentProduct = await loadProduct('Handbuch', germanMedia.id);
        store.product = repository.create();
        store.product.parentId = store.parentProduct.id;
        wrapper = await createWrapper(undefined, undefined, repository);

        await wrapper.get('.sw-inheritance-switch--is-inherited .mt-icon').trigger('click');
        const document = store.product.documents![0];
        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        await clickButton(wrapper, 'global.default.remove');
        expect(wrapper.find('.document-row').text()).toContain('english-manual.pdf');
        expect(document).toMatchObject({ title: null, mediaId: null });

        const postSpy = jest.spyOn(repository.httpClient, 'post').mockResolvedValue({ data: {} });
        await repository.save(store.product, Shopware.Context.api);
        expect(document.translations.find((translation) => translation.languageId === germanLanguageId)).toMatchObject({
            title: null,
            mediaId: null,
        });
        expect(postSpy.mock.calls[0][1]).toMatchObject({ documents: [{ id: document.id }] });
        const payload = JSON.stringify(postSpy.mock.calls[0][1]);
        expect(payload).not.toContain('Handbuch');
        expect(payload).not.toContain(germanMedia.id);
        expect(payload).toContain('Manual');
        expect(payload).toContain(englishMedia.id);
        expect(store.parentProduct.documents![0]).toMatchObject({ title: 'Handbuch', mediaId: germanMedia.id });

        postSpy.mockRestore();
        store.product = await loadProduct(null, null, store.product.id, store.parentProduct.id);
        await flushPromises();
        expect(wrapper.find('.document-row').text()).toContain('english-manual.pdf');
        expect(new ChangesetGenerator().generate(store.product)).toEqual({ changes: null, deletionQueue: [] });
    });

    it('does not offer language removal for a document using only fallback values', async () => {
        const store = Shopware.Store.get('swProductDetail');
        store.product = await loadProduct(null, null);
        wrapper = await createWrapper(undefined, undefined, repository);
        expect(wrapper.findAll('sw-context-menu-item-stub')).toHaveLength(2);
    });

    it('resets matching variant versions while retaining unrelated documents, files and order', async () => {
        const store = Shopware.Store.get('swProductDetail');
        store.product = await loadProduct('Handbuch', germanMedia.id);
        store.product.childCount = 3;
        const matching = await loadProduct('Variante', germanMedia.id, repository.create().id, store.product.id);
        const unrelated = await loadProduct('Andere', englishMedia.id, repository.create().id, store.product.id);
        const inherited = repository.create();
        inherited.parentId = store.product.id;
        const searchSpy = jest.spyOn(repository, 'search').mockResolvedValue(
            new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [
                matching,
                unrelated,
                inherited,
            ]),
        );
        const saveSpy = jest.spyOn(repository, 'save');
        wrapper = await createWrapper(undefined, undefined, repository);
        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        await wrapper.get('.sw-product-document-form__remove-from-variants input').setValue(true);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();
        expect(matching.documents).toHaveLength(1);
        expect(matching.documents![0]).toMatchObject({ title: null, mediaId: null, position: 4 });
        expect(unrelated.documents![0]).toMatchObject({ title: 'Andere', mediaId: englishMedia.id, position: 4 });
        expect(inherited.documents).toHaveLength(0);
        expect(new ChangesetGenerator().generate(store.product).deletionQueue).toEqual([]);
        expect(saveSpy).not.toHaveBeenCalled();
        expect(searchSpy.mock.calls[0][0].parse()).toMatchObject({
            associations: { documents: { associations: { translations: { associations: { media: {} } } } } },
        });
        const patchSpy = jest.spyOn(repository.httpClient, 'patch').mockResolvedValue({ data: {} });
        await repository.save(store.product, Shopware.Context.api);
        expect(patchSpy.mock.calls[0][1]).toMatchObject({
            documents: [{ id: store.product.id, title: null, mediaId: null }],
            children: [{ id: matching.id, documents: [{ id: matching.id, title: null, mediaId: null }] }],
        });
        searchSpy.mockRestore();
        store.product = await loadProduct(null, null, store.product.id);
        const reloadedVariant = await loadProduct(null, null, matching.id, store.product.id);
        expect(reloadedVariant.documents![0]).toMatchObject({
            title: null,
            mediaId: null,
            translated: { title: 'Manual', mediaId: englishMedia.id },
        });
        expect(new ChangesetGenerator().generate(store.product)).toEqual({ changes: null, deletionQueue: [] });
    });
});
