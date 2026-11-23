/**
 * @sw-package inventory
 */
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

        const operation = { type: 'remove', mediaId: media.id, title: 'manual' };
        expect(mockVariantSync).toHaveBeenCalledTimes(1);
        expect(mockVariantSync).toHaveBeenCalledWith(store.product.id, [operation], true);
        expect(store.documentVariantOperations).toEqual({ [document.id]: operation });
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
});
