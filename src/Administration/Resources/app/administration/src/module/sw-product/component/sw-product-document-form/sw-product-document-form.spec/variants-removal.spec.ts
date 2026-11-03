/**
 * @sw-package inventory
 */
import type { ComponentPublicInstance } from 'vue';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import { createWrapper, createMedia, clickButton, getDocuments } from './helpers';

function addPersistedDocument(product: Entity<'product'>, media: Entity<'media'>, position = 0) {
    const document = repositoryFactory.create('product_document').create();
    const values = { productId: product.id, title: media.fileName, mediaId: media.id, position };
    Object.assign(document, values);
    Object.assign(document.getOrigin(), values);
    document._isNew = false;
    product.documents?.add(document);
    product.getOrigin().documents = Shopware.Data.EntityCollection.fromCollection(product.documents!);
    product._isNew = false;
    return document;
}

function createVariant(media: Entity<'media'>, other?: Entity<'media'>) {
    const variant = repositoryFactory.create('product').create();
    variant.parentId = Shopware.Store.get('swProductDetail').product.id;
    addPersistedDocument(variant, media);
    if (other) {
        addPersistedDocument(variant, other, 1);
    }
    return variant;
}

describe('sw-product-document-form removal', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = repositoryFactory.create('product').create();
        store.parentProduct = repositoryFactory.create('product').create();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
    });

    it('stages only document deletions for the parent and matching variant copies', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const media = createMedia();
        const other = createMedia('other');
        const document = addPersistedDocument(store.product, media);
        addPersistedDocument(store.product, other, 1);
        store.product.childCount = 4;
        const first = createVariant(media, other);
        const second = createVariant(media);
        const custom = createVariant(other);
        const inherited = repositoryFactory.create('product').create();
        const firstDocumentId = first.documents?.[0].id;
        const secondDocumentId = second.documents?.[0].id;
        const productRepository = repositoryFactory.create('product');
        jest.spyOn(productRepository, 'search').mockResolvedValue(
            new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [
                first,
                second,
                custom,
                inherited,
            ]),
        );
        const saveSpy = jest.spyOn(productRepository, 'save');
        const mediaRepository = repositoryFactory.create('media');
        const deleteMediaSpy = jest.spyOn(mediaRepository, 'delete');
        store.documentSaveNotifications = [{ documentId: document.id, name: 'manual', variantIds: [first.id] }];
        const wrapper = await createWrapper(mediaRepository, undefined, productRepository);

        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();

        expect(getDocuments()).toHaveLength(1);
        expect(getDocuments()?.[0]).toMatchObject({ mediaId: other.id, position: 0 });
        expect(first.documents).toHaveLength(1);
        expect(first.documents?.[0]).toMatchObject({ mediaId: other.id, position: 0 });
        expect(second.documents).toHaveLength(0);
        expect(custom.documents).toHaveLength(1);
        expect(inherited.documents).toHaveLength(0);
        const payload = new ChangesetGenerator().generate(store.product);
        expect(payload).toHaveProperty('deletionQueue.length', 3);
        expect(payload).toHaveProperty(
            'deletionQueue',
            expect.arrayContaining([
                expect.objectContaining({ entity: 'product_document', key: document.id }),
                expect.objectContaining({ entity: 'product_document', key: firstDocumentId }),
                expect.objectContaining({ entity: 'product_document', key: secondDocumentId }),
            ]),
        );
        expect(store.documentSaveNotifications).toHaveLength(0);
        expect(saveSpy).not.toHaveBeenCalled();
        expect(deleteMediaSpy).not.toHaveBeenCalled();
    });

    it('preserves the saved order of remaining variant documents when removing a matching copy', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const media = createMedia('remove-middle');
        addPersistedDocument(store.product, media);
        store.product.childCount = 1;
        const variant = repositoryFactory.create('product').create();
        variant.parentId = store.product.id;
        const first = addPersistedDocument(variant, createMedia('first'), 0);
        const middle = addPersistedDocument(variant, media, 1);
        const last = addPersistedDocument(variant, createMedia('last'), 2);
        const repository = repositoryFactory.create('product');
        const searchSpy = jest
            .spyOn(repository, 'search')
            .mockResolvedValue(
                new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [variant]),
            );
        const wrapper = await createWrapper(undefined, undefined, repository);
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();

        expect(searchSpy.mock.calls[0][0].parse()).toMatchObject({
            associations: { documents: { sort: [{ field: 'position', order: 'ASC' }] } },
        });
        expect(Array.from(variant.documents!, (document) => ({ id: document.id, position: document.position }))).toEqual([
            { id: first.id, position: 0 },
            { id: last.id, position: 1 },
        ]);
        expect(new ChangesetGenerator().generate(store.product).deletionQueue).toEqual(
            expect.arrayContaining([expect.objectContaining({ entity: 'product_document', key: middle.id })]),
        );
    });

    it('removes only the parent assignment when the variant option is off', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const media = createMedia();
        addPersistedDocument(store.product, media);
        store.product.childCount = 1;
        const productRepository = repositoryFactory.create('product');
        const searchSpy = jest.spyOn(productRepository, 'search');
        const wrapper = await createWrapper(undefined, undefined, productRepository);

        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();

        expect(getDocuments()).toHaveLength(0);
        expect(searchSpy).not.toHaveBeenCalled();
        expect(store.product.children).toHaveLength(0);
    });

    it('keeps nested deletions when a variant already has staged document edits', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const original = createMedia();
        const replacement = createMedia('replacement');
        addPersistedDocument(store.product, original);
        const variant = createVariant(original);
        const copy = variant.documents![0];
        getDocuments()![0].mediaId = replacement.id;
        copy.mediaId = replacement.id;
        store.product.children!.add(variant);
        store.product.childCount = 1;
        const productRepository = repositoryFactory.create('product');
        jest.spyOn(productRepository, 'search').mockResolvedValue(
            new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api),
        );
        const wrapper = await createWrapper(undefined, undefined, productRepository);
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();

        expect(store.product.children).toHaveLength(1);
        expect(variant.documents).toHaveLength(0);
        const payload = new ChangesetGenerator().generate(store.product);
        expect(payload).toHaveProperty('deletionQueue.length', 2);
        expect(payload).toHaveProperty(
            'deletionQueue',
            expect.arrayContaining([
                expect.objectContaining({ entity: 'product_document', key: copy.id }),
            ]),
        );
    });

    it('resets the variant option on reopening and preserves assignments on Cancel', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const media = createMedia();
        addPersistedDocument(store.product, media);
        store.product.childCount = 1;
        const wrapper = await createWrapper();
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.cancel');
        expect(getDocuments()).toHaveLength(1);
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__remove-from-variants input').element.checked).toBe(
            false,
        );
    });

    it('keeps the confirmation open and leaves the draft unchanged when variants cannot be loaded', async () => {
        const store = Shopware.Store.get('swProductDetail');
        addPersistedDocument(store.product, createMedia());
        store.product.childCount = 1;
        const productRepository = repositoryFactory.create('product');
        jest.spyOn(productRepository, 'search').mockRejectedValue(new Error('Load failed'));
        const wrapper = await createWrapper(undefined, undefined, productRepository);
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();

        expect(getDocuments()).toHaveLength(1);
        expect(store.product.children).toHaveLength(0);
        expect(wrapper.find('.sw-product-document-form__confirm-remove').exists()).toBe(true);
    });

    it('discards a late variant lookup if the confirmation was cancelled and reopened', async () => {
        const store = Shopware.Store.get('swProductDetail');
        addPersistedDocument(store.product, createMedia());
        store.product.childCount = 1;
        const variants = new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api);
        let resolveVariants: (result: typeof variants) => void = () => {};
        const productRepository = repositoryFactory.create('product');
        jest.spyOn(productRepository, 'search').mockReturnValue(
            new Promise((resolve) => {
                resolveVariants = resolve;
            }),
        );
        const wrapper = await createWrapper(undefined, undefined, productRepository);
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.remove');
        await clickButton(wrapper, 'global.default.cancel');
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        resolveVariants(variants);
        await flushPromises();

        expect(getDocuments()).toHaveLength(1);
        expect(store.product.children).toHaveLength(0);
        expect(wrapper.find('.sw-product-document-form__confirm-remove').exists()).toBe(true);
    });
});
