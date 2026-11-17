/**
 * @sw-package inventory
 */
import type { VueWrapper } from '@vue/test-utils';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import {
    createProduct,
    cleanupWrappers,
    createWrapper,
    createMedia,
    addMedia,
    clickButton,
    selectMedia,
    getDocuments,
} from '../sw-product-document-form.spec/helpers';

describe('sw-product-document-form variant badges', () => {
    let wrapper: VueWrapper;
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
        store.product.parentId = store.parentProduct.id;
    });

    afterEach(() => {
        wrapper?.unmount();
        Shopware.Context.api.languageId = originalLanguage;
        cleanupWrappers();
    });

    async function unlinkDocuments() {
        const media = createMedia('parent-manual');
        const document = repositoryFactory.create('product_document').create();
        Object.assign(document, {
            mediaId: media.id,
            title: 'Parent manual',
            position: 0,
            translations: [{ languageId: Shopware.Context.api.systemLanguageId, mediaId: media.id, media }],
        });
        Shopware.Store.get('swProductDetail').parentProduct.documents?.add(document);
        wrapper = await createWrapper();
        await wrapper.get('.sw-inheritance-switch--is-inherited .mt-icon').trigger('click');
        return media;
    }

    it('marks newly added files beside their names and leaves inherited copies unbadged', async () => {
        await unlinkDocuments();
        // A different media entity can have the same filename as a parent file.
        await addMedia(wrapper, [createMedia('parent-manual')]);
        const rows = wrapper.findAll('.document-row');
        expect(rows[0].find('.sw-product-document-variant-badge').exists()).toBe(false);
        expect(rows[1].findAll('.sw-product-document-variant-badge')).toHaveLength(1);
        expect(rows[1].get('.sw-product-document-language-value').text()).toContain('sw-product.documentForm.variantOnly');
    });

    it('does not mark a parent file removed from the variant and then added again', async () => {
        const media = await unlinkDocuments();
        await wrapper.findAll('sw-context-menu-item-stub').at(-1)!.trigger('click');
        await flushPromises();
        await clickButton(wrapper, 'global.default.remove');
        expect(getDocuments()).toHaveLength(0);

        await addMedia(wrapper, [media]);

        expect(getDocuments()).toHaveLength(1);
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(false);
    });

    it('keeps a replacement file, variant badge and download after remounting an unlinked variant', async () => {
        Object.assign(global, { activeAclRoles: ['product.editor', 'media.viewer'] });
        await unlinkDocuments();
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await flushPromises();
        await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
        const replacement = createMedia('replacement');
        await selectMedia(wrapper, [replacement]);
        await clickButton(wrapper, 'global.default.apply');

        expect(wrapper.text()).toContain('replacement.pdf');
        wrapper.unmount();
        const mediaService = {
            prepareDownloadMedia: jest
                .fn()
                .mockResolvedValue({ type: 'external', url: 'https://example.com/replacement.pdf' }),
            downloadMedia: jest.fn(),
        };
        wrapper = await createWrapper(undefined, mediaService);
        expect(wrapper.text()).toContain('replacement.pdf');
        expect(wrapper.text()).not.toContain('parent-manual.pdf');
        await wrapper.get('.sw-product-document-form__download').trigger('click');
        await flushPromises();
        expect(mediaService.prepareDownloadMedia).toHaveBeenCalledWith(replacement.id);
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(true);
    });

    it('does not mark documents added to a parent product', async () => {
        Shopware.Store.get('swProductDetail').product.parentId = undefined;
        wrapper = await createWrapper();
        await addMedia(wrapper, [createMedia()]);
        expect(getDocuments()).toHaveLength(1);
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(false);
    });

    it('keeps the unsaved badge when returning to the form after a tab remount', async () => {
        await unlinkDocuments();
        await addMedia(wrapper, [createMedia('variant-manual')]);
        const document = getDocuments()![1];
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(true);
        wrapper.unmount();
        wrapper = await createWrapper();
        expect(getDocuments()![1]).toBe(document);
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(true);
        expect(wrapper.text()).toContain('variant-manual.pdf');
    });

    it('keeps the variant badge after reloading new entities and reopening the form', async () => {
        await unlinkDocuments();
        const media = createMedia('variant-manual');
        await addMedia(wrapper, [media]);
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(true);
        const store = Shopware.Store.get('swProductDetail');
        const reloaded = createProduct(store.product.id);
        reloaded.parentId = store.parentProduct.id;
        const document = repositoryFactory.create('product_document').create(Shopware.Context.api, getDocuments()![1].id);
        Object.assign(document, { mediaId: media.id, title: media.fileName, position: 0 });
        reloaded.documents?.add(document);
        store.product = reloaded;
        await flushPromises();

        expect(wrapper.text()).toContain('variant-manual');
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(true);
        wrapper.unmount();
        wrapper = await createWrapper();
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(true);
    });

    it('removes the variant badge when the file is also used by a parent translation', async () => {
        await unlinkDocuments();
        const media = createMedia('variant-manual');
        await addMedia(wrapper, [media]);
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(true);

        const parentDocument = Shopware.Store.get('swProductDetail').parentProduct.documents![0];
        Object.assign(parentDocument, {
            translations: [{ languageId: 'other-language', mediaId: media.id, media }],
        });
        await flushPromises();
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(false);
    });

    it('drops badges when inheritance is restored and does not mark newly copied documents', async () => {
        await unlinkDocuments();
        await addMedia(wrapper, [createMedia('variant-manual')]);
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(true);

        await wrapper.get('.sw-inheritance-switch--is-not-inherited .mt-icon').trigger('click');
        await wrapper.get('.sw-product-document-form__confirm-restore-inheritance').trigger('click');
        await wrapper.get('.sw-inheritance-switch--is-inherited .mt-icon').trigger('click');

        expect(getDocuments()).toHaveLength(1);
        expect(wrapper.find('.sw-product-document-variant-badge').exists()).toBe(false);
    });
});
