/**
 * @sw-package inventory
 */
import type { ComponentPublicInstance } from 'vue';
import { config } from '@vue/test-utils';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import {
    mockSnackbar,
    mockVariantSync,
    createProduct,
    cleanupWrappers,
    createWrapper,
    createMedia,
    clickButton,
    selectMedia,
    getDocuments,
} from './helpers';

function addPersistedDocument(product: Entity<'product'>, media: Entity<'media'>, position = 0) {
    const document = repositoryFactory.create('product_document').create();
    const values = {
        productId: product.id,
        title: media.fileName,
        mediaId: media.id,
        position,
        translated: { title: media.fileName, mediaId: media.id },
    };
    Object.assign(document, values);
    Object.assign(document.getOrigin(), values);
    document._isNew = false;
    product.documents?.add(document);
    product.getOrigin().documents = Shopware.Data.EntityCollection.fromCollection(product.documents!);
    product._isNew = false;
    return document;
}

describe('sw-product-document-form variant removal', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        mockSnackbar.mockClear();
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        cleanupWrappers();
        jest.restoreAllMocks();
    });

    it('removes only the parent document from the payload and queues the variant removal after a dry run', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const media = createMedia();
        const other = createMedia('other');
        const document = addPersistedDocument(store.product, media);
        const remaining = addPersistedDocument(store.product, other, 1);
        store.product.childCount = 5;
        mockVariantSync.mockResolvedValueOnce(['first-variant', 'second-variant']);
        const productRepository = repositoryFactory.create('product');
        const searchSpy = jest.spyOn(productRepository, 'search');
        const saveSpy = jest.spyOn(productRepository, 'save');
        const mediaRepository = repositoryFactory.create('media');
        const deleteMediaSpy = jest.spyOn(mediaRepository, 'delete');
        const translate = jest.spyOn(config.global.mocks, '$t');
        const wrapper = await createWrapper(mediaRepository, undefined, productRepository);

        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__remove-from-variants input').element.checked).toBe(
            true,
        );
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();

        const operation = { mediaId: media.id, title: 'manual' };
        expect(mockVariantSync).toHaveBeenCalledTimes(1);
        expect(mockVariantSync).toHaveBeenCalledWith(store.product.id, [operation], true);
        expect(store.documentVariantOperations).toEqual([operation]);
        expect(getDocuments()).toHaveLength(1);
        expect(getDocuments()?.[0].id).toBe(remaining.id);
        expect(getDocuments()?.[0]).toMatchObject({ mediaId: other.id, position: 0 });
        expect(store.product.children).toHaveLength(0);
        const payload = new ChangesetGenerator().generate(store.product);
        expect(payload.deletionQueue).toEqual([
            expect.objectContaining({ entity: 'product_document', key: document.id }),
        ]);
        expect(payload.changes).not.toHaveProperty('children');
        expect(mockSnackbar).toHaveBeenCalledTimes(1);
        expect(mockSnackbar).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'success',
                message: 'sw-product.documentForm.removeSuccess',
            }),
        );
        expect(translate).toHaveBeenCalledWith(
            'sw-product.documentForm.removeSuccess',
            expect.objectContaining({ name: 'manual', count: 2 }),
        );
        expect(searchSpy).not.toHaveBeenCalled();
        expect(saveSpy).not.toHaveBeenCalled();
        expect(deleteMediaSpy).not.toHaveBeenCalled();
    });

    it('removes only the parent assignment when the variant option is off', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const media = createMedia();
        addPersistedDocument(store.product, media);
        store.product.childCount = 1;
        const wrapper = await createWrapper();

        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        await wrapper.get('.sw-product-document-form__remove-from-variants input').setValue(false);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();

        expect(getDocuments()).toHaveLength(0);
        expect(mockVariantSync).not.toHaveBeenCalled();
        expect(store.documentVariantOperations).toEqual([]);
        expect(store.product.children).toHaveLength(0);
    });

    it('queues the removal of the edited file after an earlier queued variant edit', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const original = createMedia();
        const replacement = createMedia('replacement');
        const document = addPersistedDocument(store.product, original);
        store.product.childCount = 1;
        mockVariantSync.mockResolvedValueOnce(['variant-a']).mockResolvedValueOnce(['variant-a']);
        const wrapper = await createWrapper();
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await selectMedia(wrapper, [replacement]);
        await clickButton(wrapper, 'global.default.apply');
        await flushPromises();

        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();

        const removal = { mediaId: replacement.id, title: 'manual' };
        expect(mockVariantSync).toHaveBeenCalledTimes(2);
        expect(mockVariantSync).toHaveBeenLastCalledWith(store.product.id, [removal], true);
        expect(store.documentVariantOperations).toEqual([
            { mediaId: original.id, title: 'manual', changes: { mediaId: replacement.id } },
            removal,
        ]);
        expect(getDocuments()).toHaveLength(0);
        expect(store.product.children).toHaveLength(0);
        expect(new ChangesetGenerator().generate(store.product).deletionQueue).toEqual([
            expect.objectContaining({ entity: 'product_document', key: document.id }),
        ]);
    });

    it('resets the variant option on reopening and preserves assignments on Cancel', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const media = createMedia();
        addPersistedDocument(store.product, media);
        store.product.childCount = 1;
        const wrapper = await createWrapper();
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        await wrapper.get('.sw-product-document-form__remove-from-variants input').setValue(false);
        await clickButton(wrapper, 'global.default.cancel');
        expect(getDocuments()).toHaveLength(1);
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__remove-from-variants input').element.checked).toBe(
            true,
        );
    });

    it('keeps the confirmation open and leaves the draft unchanged when variants cannot be loaded', async () => {
        const store = Shopware.Store.get('swProductDetail');
        addPersistedDocument(store.product, createMedia());
        store.product.childCount = 1;
        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        mockVariantSync.mockRejectedValueOnce(new Error('Load failed'));
        const wrapper = await createWrapper();
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();

        expect(getDocuments()).toHaveLength(1);
        expect(store.documentVariantOperations).toEqual([]);
        expect(store.product.children).toHaveLength(0);
        expect(wrapper.get('.sw-product-document-form__confirm-remove').attributes('disabled')).toBeUndefined();
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'error', message: 'sw-product.documentForm.errorLoadVariants' }),
        );
        expect(mockSnackbar).not.toHaveBeenCalled();
    });

    it('keeps the confirmation open with Cancel disabled until the variant dry run resolves', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const media = createMedia();
        addPersistedDocument(store.product, media);
        store.product.childCount = 1;
        let resolveSync!: (variantIds: string[]) => void;
        mockVariantSync.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveSync = resolve;
                }),
        );
        const wrapper = await createWrapper();
        await wrapper.findAll('sw-context-menu-item-stub')[2].trigger('click');
        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__remove-from-variants')
            .vm.$emit('update:checked', true);
        await clickButton(wrapper, 'global.default.remove');
        await flushPromises();

        const cancel = wrapper.findAll('button').find((button) => button.text() === 'global.default.cancel')!;
        expect(cancel.attributes('disabled')).toBeDefined();
        expect(wrapper.get('.sw-product-document-form__confirm-remove').attributes('disabled')).toBeDefined();
        await cancel.trigger('click');
        expect(wrapper.find('.sw-product-document-form__confirm-remove').exists()).toBe(true);
        expect(getDocuments()).toHaveLength(1);
        expect(store.documentVariantOperations).toEqual([]);

        resolveSync(['variant-a']);
        await flushPromises();

        expect(wrapper.find('.sw-product-document-form__confirm-remove').exists()).toBe(false);
        expect(getDocuments()).toHaveLength(0);
        expect(store.documentVariantOperations).toEqual([{ mediaId: media.id, title: 'manual' }]);
        expect(mockSnackbar).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'success', message: 'sw-product.documentForm.removeSuccess' }),
        );
    });
});
