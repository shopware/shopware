/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import {
    mockSnackbar,
    createProduct,
    cleanupWrappers,
    createWrapper,
    createMedia,
    addDocuments,
    clickButton,
    getDocuments,
    mockVariantSync,
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
        mockSnackbar.mockClear();
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

    it('queues the variant removal of every selected document without a dry run', async () => {
        const wrapper = await createWrapper();
        const media = [createMedia('first'), createMedia('second')];
        await addDocuments(wrapper, media);
        const documents = [...getDocuments()!];
        const store = Shopware.Store.get('swProductDetail');
        store.product.childCount = 1;

        await selectDocuments(wrapper, documents);
        expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__remove-from-variants input').element.checked).toBe(
            true,
        );
        await wrapper.get('.sw-product-document-form__confirm-remove').trigger('click');
        await flushPromises();

        expect(mockVariantSync).not.toHaveBeenCalled();
        expect(getDocuments()).toHaveLength(0);
        expect(store.documentVariantOperations).toEqual({
            [documents[0].id]: { type: 'remove', mediaId: media[0].id, title: 'first' },
            [documents[1].id]: { type: 'remove', mediaId: media[1].id, title: 'second' },
        });
        expect(wrapper.find('.sw-product-document-form__remove-content').exists()).toBe(false);
        expect(mockSnackbar).toHaveBeenCalledTimes(1);
        expect(mockSnackbar).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'success', message: 'sw-product.documentForm.bulkRemoveSuccess' }),
        );
    });

    it('clears only the selected language versions and keeps the assignments', async () => {
        const wrapper = await createWrapper();
        await addDocuments(wrapper, [createMedia('first'), createMedia('second')]);
        const documents = [...getDocuments()!];
        const language = repositoryFactory.create('language').create();
        Object.assign(language, { name: 'German' });
        Shopware.Context.api.languageId = language.id;
        Shopware.Store.get('context').api.language = language;
        await flushPromises();

        await selectDocuments(wrapper, documents);
        await wrapper.get('.sw-product-document-form__confirm-remove').trigger('click');
        await flushPromises();
        expect(getDocuments()).toHaveLength(2);
        documents.forEach((document) => expect(document).toMatchObject({ title: null, mediaId: null }));
    });
});
