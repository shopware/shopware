/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import {
    createProduct,
    cleanupWrappers,
    createWrapper,
    createMedia,
    addDocuments,
    clickButton,
    getDocuments,
} from './helpers';

async function selectDocuments(wrapper: VueWrapper, documents: Entity<'product_document'>[]) {
    const grid = wrapper.getComponent<ComponentPublicInstance>({ ref: 'documentGrid' });
    await grid.setData({ hasSelection: true });
    grid.vm.$emit('select-item', Object.fromEntries(documents.map((document) => [document.id, document])));
    await flushPromises();
    await wrapper.get('.mt-link--critical').trigger('click');
}

describe('sw-product-document-form bulk removal', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    const originalLanguage = Shopware.Store.get('context').api.language;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguageId;
        Shopware.Store.get('context').api.language = originalLanguage;
        cleanupWrappers();
    });

    it('cancels without changing documents, then removes only the selected documents from the draft', async () => {
        const repository = repositoryFactory.create('product');
        const save = jest.spyOn(repository, 'save');
        const wrapper = await createWrapper(undefined, undefined, repository);
        await addDocuments(wrapper, [createMedia('first'), createMedia('keep'), createMedia('last')]);
        const documents = [...getDocuments()!];

        await selectDocuments(wrapper, [documents[0], documents[2]]);
        expect(wrapper.get('.upload-modal').attributes('title')).toBe('sw-product.documentForm.bulkRemoveTitle');
        await clickButton(wrapper, 'global.default.cancel');
        expect([...getDocuments()!]).toEqual(documents);

        await wrapper.get('.mt-link--critical').trigger('click');
        await wrapper.get('.sw-product-document-form__confirm-remove').trigger('click');
        await flushPromises();

        expect(getDocuments()).toHaveLength(1);
        expect(getDocuments()![0].id).toBe(documents[1].id);
        expect(getDocuments()![0].position).toBe(0);
        expect(wrapper.find('.mt-link--critical').exists()).toBe(false);
        expect(save).not.toHaveBeenCalled();
    });

    it('loads matching variants together and leaves the draft untouched on failure before retrying', async () => {
        const repository = repositoryFactory.create('product');
        const search = jest.spyOn(repository, 'search');
        const wrapper = await createWrapper(undefined, undefined, repository);
        const media = [createMedia('first'), createMedia('second')];
        await addDocuments(wrapper, media);
        const store = Shopware.Store.get('swProductDetail');
        store.product.childCount = 1;
        const variant = createProduct();
        variant.parentId = store.product.id;
        getDocuments()!.forEach((document) => {
            const copy = repositoryFactory.create('product_document').create();
            Object.assign(copy, {
                productId: variant.id,
                title: document.title,
                mediaId: document.mediaId,
                position: document.position,
            });
            variant.documents!.add(copy);
        });
        search.mockRejectedValueOnce(new Error('Unavailable'));
        await selectDocuments(wrapper, [...getDocuments()!]);
        await wrapper.get('.sw-product-document-form__confirm-remove').trigger('click');
        await flushPromises();
        expect(getDocuments()).toHaveLength(2);
        expect(variant.documents).toHaveLength(2);
        expect(wrapper.find('.sw-product-document-form__remove-content').exists()).toBe(true);

        search.mockResolvedValueOnce(
            new Shopware.Data.EntityCollection('/product', 'product', Shopware.Context.api, null, [variant]),
        );
        await wrapper.get('.sw-product-document-form__confirm-remove').trigger('click');
        await flushPromises();
        expect(search).toHaveBeenCalledTimes(2);
        expect(search.mock.calls[1][0]?.filters).toContainEqual({
            type: 'equalsAny',
            field: 'documents.mediaId',
            value: media.map((file) => file.id).join('|'),
        });
        expect(getDocuments()).toHaveLength(0);
        expect(variant.documents).toHaveLength(0);
    });

    it('clears only the selected language versions and keeps the assignments', async () => {
        const wrapper = await createWrapper();
        await addDocuments(wrapper, [createMedia('first'), createMedia('second')]);
        const documents = [...getDocuments()!];
        const language = repositoryFactory.create('language').create();
        Object.assign(language, { name: 'German' });
        Shopware.Context.api.languageId = language.id;
        Shopware.Store.get('context').api.language = language;
        Shopware.Store.get('swProductDetail').documentLanguage = language;
        await flushPromises();

        await selectDocuments(wrapper, documents);
        await wrapper.get('.sw-product-document-form__confirm-remove').trigger('click');
        await flushPromises();
        expect(getDocuments()).toHaveLength(2);
        documents.forEach((document) => expect(document).toMatchObject({ title: null, mediaId: null }));
    });
});
