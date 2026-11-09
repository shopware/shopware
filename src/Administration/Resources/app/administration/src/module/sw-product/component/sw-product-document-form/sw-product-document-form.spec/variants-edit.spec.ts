/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { ComponentPublicInstance } from 'vue';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';

import { cleanupWrappers, createWrapper, createMedia, clickButton, selectMedia, addMedia, getDocuments } from './helpers';

describe('sw-product-document-form variant editing', () => {
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
        cleanupWrappers();
    });

    describe('applying document edits to variants', () => {
        function createVariant(media: Entity<'media'>) {
            const variant = repositoryFactory.create('product').create();
            variant.parentId = Shopware.Store.get('swProductDetail').product.id;
            const document = repositoryFactory.create('product_document').create();
            Object.assign(document, { productId: variant.id, title: 'Variant title', mediaId: media.id, position: 3 });
            Object.assign(document.getOrigin(), {
                productId: variant.id,
                title: 'Variant title',
                mediaId: media.id,
                position: 3,
            });
            document._isNew = false;
            variant.documents?.add(document);
            variant.getOrigin().documents = Shopware.Data.EntityCollection.fromCollection(variant.documents!);
            variant._isNew = false;
            return variant;
        }

        it('stages matching copies for two variants in the product payload without saving immediately', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 4;
            const original = createMedia();
            const replacement = createMedia('replacement');
            const first = createVariant(original);
            const second = createVariant(original);
            const custom = createVariant(createMedia('custom'));
            const inherited = repositoryFactory.create('product').create();
            const variants = new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [
                first,
                second,
                custom,
                inherited,
            ]);
            const productRepository = repositoryFactory.create('product');
            const searchSpy = jest.spyOn(productRepository, 'search').mockResolvedValue(variants);
            const saveSpy = jest.spyOn(productRepository, 'save');
            const wrapper = await createWrapper(undefined, undefined, productRepository);
            await addMedia(wrapper, [original]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
            await selectMedia(wrapper, [replacement]);
            wrapper
                .getComponent<ComponentPublicInstance>('.mt-text-field')
                .vm.$emit('update:modelValue', 'Battery safety data sheet');
            expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__apply-to-variants input').element.checked).toBe(
                true,
            );
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            expect(first.documents?.[0]).toMatchObject({
                title: 'Battery safety data sheet',
                mediaId: replacement.id,
                position: 3,
            });
            expect(second.documents?.[0].mediaId).toBe(replacement.id);
            expect(custom.documents?.[0].title).toBe('Variant title');
            expect(inherited.documents).toHaveLength(0);
            expect(store.product.children).toHaveLength(2);
            const payload = new ChangesetGenerator().generate(store.product);
            expect(payload).toHaveProperty('deletionQueue.length', 0);
            expect(payload).toHaveProperty(
                'changes.children',
                expect.arrayContaining([
                    expect.objectContaining({
                        id: first.id,
                        documents: [
                            expect.objectContaining({
                                id: first.documents?.[0].id,
                                title: 'Battery safety data sheet',
                                mediaId: replacement.id,
                            }),
                        ],
                    }),
                    expect.objectContaining({
                        id: second.id,
                        documents: [expect.objectContaining({ mediaId: replacement.id })],
                    }),
                ]),
            );
            expect(store.documentSaveNotifications).toEqual([
                { documentId: getDocuments()?.[0].id, name: 'Battery safety data sheet', variantIds: [first.id, second.id] },
            ]);
            expect(searchSpy).toHaveBeenCalledWith(
                expect.objectContaining({
                    filters: [
                        { type: 'equals', field: 'parentId', value: store.product.id },
                        { type: 'equals', field: 'documents.mediaId', value: original.id },
                    ],
                }),
                expect.objectContaining({ inheritance: false }),
            );
            expect(saveSpy).not.toHaveBeenCalled();
        });

        it('keeps variant documents unchanged when the checkbox is off', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 1;
            const productRepository = repositoryFactory.create('product');
            const searchSpy = jest.spyOn(productRepository, 'search');
            const wrapper = await createWrapper(undefined, undefined, productRepository);
            await addMedia(wrapper, [createMedia()]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Parent only');
            await wrapper.get('.sw-product-document-form__apply-to-variants input').setValue(false);
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            expect(searchSpy).not.toHaveBeenCalled();
            expect(store.product.children).toHaveLength(0);
            expect(store.documentSaveNotifications).toHaveLength(0);
            expect(getDocuments()?.[0].title).toBe('Parent only');
        });

        it('resets the variant option to checked on reopening without applying cancelled edits', async () => {
            Shopware.Store.get('swProductDetail').product.childCount = 1;
            const wrapper = await createWrapper();
            await addMedia(wrapper, [createMedia()]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await wrapper.get('.sw-product-document-form__apply-to-variants input').setValue(false);
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Cancelled title');

            await clickButton(wrapper, 'global.default.cancel');
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');

            expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__apply-to-variants input').element.checked).toBe(
                true,
            );
            expect(getDocuments()?.[0].title).toBe('manual');
            expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__name-input input').element.value).toBe(
                'manual',
            );
        });

        it('does not report variant updates when a replacement is reverted before saving the product', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 1;
            const original = createMedia();
            const variant = createVariant(original);
            const productRepository = repositoryFactory.create('product');
            const searchSpy = jest
                .spyOn(productRepository, 'search')
                .mockResolvedValueOnce(
                    new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [variant]),
                )
                .mockResolvedValueOnce(new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api));
            const wrapper = await createWrapper(undefined, undefined, productRepository);
            await addMedia(wrapper, [original]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
            await selectMedia(wrapper, [createMedia('replacement')]);
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Variant title');
            wrapper
                .getComponent<ComponentPublicInstance>('.sw-product-document-form__apply-to-variants')
                .vm.$emit('update:checked', true);
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();
            expect(store.documentSaveNotifications[0].variantIds).toEqual([variant.id]);
            const previousMediaId = variant.documents![0].mediaId;

            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
            await selectMedia(wrapper, [original]);
            wrapper
                .getComponent<ComponentPublicInstance>('.sw-product-document-form__apply-to-variants')
                .vm.$emit('update:checked', true);
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            expect(searchSpy.mock.calls[1][0].filters).toEqual([
                { type: 'equals', field: 'parentId', value: store.product.id },
                { type: 'equals', field: 'documents.mediaId', value: previousMediaId },
            ]);
            expect(variant.documents?.[0].mediaId).toBe(original.id);
            expect(store.documentSaveNotifications).toHaveLength(0);
        });

        it('updates matching variants beyond the first page of results', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 101;
            const media = createMedia();
            const firstPage = Array.from({ length: 100 }, () => createVariant(media));
            const last = createVariant(media);
            const productRepository = repositoryFactory.create('product');
            const searchSpy = jest
                .spyOn(productRepository, 'search')
                .mockResolvedValueOnce(
                    new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, firstPage),
                )
                .mockResolvedValueOnce(
                    new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [last]),
                );
            const wrapper = await createWrapper(undefined, undefined, productRepository);
            await addMedia(wrapper, [media]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'New title');
            wrapper
                .getComponent<ComponentPublicInstance>('.sw-product-document-form__apply-to-variants')
                .vm.$emit('update:checked', true);
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            expect(searchSpy).toHaveBeenCalledTimes(2);
            expect(searchSpy.mock.calls[0][0].page).toBe(1);
            expect(searchSpy).toHaveBeenNthCalledWith(
                2,
                expect.objectContaining({ page: 2, limit: 100, totalCountMode: 0 }),
                expect.objectContaining({ inheritance: false }),
            );
            expect(last.documents?.[0].title).toBe('New title');
            expect(store.documentSaveNotifications[0].variantIds).toHaveLength(101);
        });

        it('discards a cancelled lookup after reopening the same document', async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.product.childCount = 1;
            const original = createMedia();
            const variant = createVariant(original);
            const productRepository = repositoryFactory.create('product');
            let resolveSearch!: (variants: EntityCollection<'product'>) => void;
            jest.spyOn(productRepository, 'search')
                .mockResolvedValue(new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api))
                .mockImplementationOnce(
                    () =>
                        new Promise((resolve) => {
                            resolveSearch = resolve;
                        }),
                );
            const wrapper = await createWrapper(undefined, undefined, productRepository);
            await addMedia(wrapper, [original]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Cancelled title');
            wrapper
                .getComponent<ComponentPublicInstance>('.sw-product-document-form__apply-to-variants')
                .vm.$emit('update:checked', true);
            await clickButton(wrapper, 'global.default.apply');
            await clickButton(wrapper, 'global.default.cancel');
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'New draft title');

            resolveSearch(new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [variant]));
            await flushPromises();

            expect(getDocuments()![0].title).toBe('manual');
            expect(variant.documents![0].title).toBe('Variant title');
            expect(store.product.children).toHaveLength(0);
            expect(store.documentSaveNotifications).toHaveLength(0);
            expect(wrapper.find('.sw-product-document-form__save-document').exists()).toBe(true);
            expect(wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').props()).toMatchObject({
                modelValue: 'New draft title',
            });
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();
            expect(getDocuments()![0].title).toBe('New draft title');
        });

        it('keeps the original document and editor open when loading variants fails', async () => {
            Shopware.Store.get('swProductDetail').product.childCount = 1;
            const productRepository = repositoryFactory.create('product');
            jest.spyOn(productRepository, 'search').mockRejectedValue(new Error('Load failed'));
            const wrapper = await createWrapper(undefined, undefined, productRepository);
            await addMedia(wrapper, [createMedia()]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'New title');
            wrapper
                .getComponent<ComponentPublicInstance>('.sw-product-document-form__apply-to-variants')
                .vm.$emit('update:checked', true);
            await clickButton(wrapper, 'global.default.apply');
            await flushPromises();

            expect(getDocuments()?.[0].title).toBe('manual');
            expect(wrapper.find('.sw-product-document-form__save-document').exists()).toBe(true);
            expect(Shopware.Store.get('swProductDetail').documentSaveNotifications).toHaveLength(0);
        });
    });
});
