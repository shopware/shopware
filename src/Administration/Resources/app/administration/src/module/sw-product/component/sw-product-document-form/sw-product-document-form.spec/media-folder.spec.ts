/**
 * @sw-package inventory
 */
import type { ComponentPublicInstance } from 'vue';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import { cleanupWrappers, createWrapper, createMedia, addMedia, clickButton, getDocuments } from './helpers';

describe('sw-product-document-form media folder', () => {
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
        cleanupWrappers();
    });

    it.each([false, true])('starts in the document folder when selecting media (replacement: %s)', async (replacement) => {
        const folderId = repositoryFactory.create('media_folder').create().id;
        const getDefaultFolderId = jest.fn().mockResolvedValue(folderId);
        const wrapper = await createWrapper(undefined, undefined, undefined, { getDefaultFolderId });
        expect(getDefaultFolderId).toHaveBeenCalledWith('product_document');
        if (replacement) {
            await addMedia(wrapper, [createMedia('original')]);
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
        } else {
            await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');
        }
        wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2').vm.$emit('media-upload-sidebar-open');
        await flushPromises();

        const modal = wrapper.getComponent<ComponentPublicInstance>('sw-media-modal-v2-stub');
        expect(modal.attributes('initial-folder-id')).toBe(folderId);
        const media = createMedia('selected');
        modal.vm.$emit('media-modal-selection-change', [media]);
        await flushPromises();
        if (replacement) {
            await clickButton(wrapper, 'global.default.apply');
        }
        expect(getDocuments()).toHaveLength(1);
        expect(getDocuments()?.[0].mediaId).toBe(media.id);
    });

    it('waits for the folder before mounting the media picker', async () => {
        let resolveFolder!: (id: string) => void;
        const folder = new Promise<string>((resolve) => {
            resolveFolder = resolve;
        });
        const wrapper = await createWrapper(undefined, undefined, undefined, { getDefaultFolderId: () => folder });
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');
        wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2').vm.$emit('media-upload-sidebar-open');
        await flushPromises();
        expect(wrapper.find('sw-media-modal-v2-stub').exists()).toBe(false);

        resolveFolder('document-folder');
        await flushPromises();
        expect(wrapper.get('sw-media-modal-v2-stub').attributes('initial-folder-id')).toBe('document-folder');
    });

    it.each(['missing', 'failed'])('opens the media root when the document folder is %s', async (result) => {
        const getDefaultFolderId = jest.fn().mockResolvedValue(null);
        if (result === 'failed') {
            getDefaultFolderId.mockRejectedValueOnce(new Error('Folder unavailable'));
        }
        const wrapper = await createWrapper(undefined, undefined, undefined, { getDefaultFolderId });
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');
        wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2').vm.$emit('media-upload-sidebar-open');
        await flushPromises();
        const modal = wrapper.getComponent<ComponentPublicInstance>('sw-media-modal-v2-stub');
        expect(modal.attributes('initial-folder-id')).toBeUndefined();
        const media = createMedia();
        modal.vm.$emit('media-modal-selection-change', [media]);
        await flushPromises();
        expect(getDocuments()?.[0].mediaId).toBe(media.id);
    });
});
