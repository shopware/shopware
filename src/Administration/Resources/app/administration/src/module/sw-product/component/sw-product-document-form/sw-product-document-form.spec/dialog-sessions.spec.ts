/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { ComponentPublicInstance } from 'vue';
import { createProduct, cleanupWrappers, createWrapper, createMedia, addMedia, clickButton, getDocuments } from './helpers';

describe('sw-product-document-form dialog sessions', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
        store.product.childCount = 1;
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        cleanupWrappers();
    });

    it.each([false, true])('ignores a media lookup after leaving the tab (reject: %s)', async (reject) => {
        const repository = repositoryFactory.create('media');
        const media = createMedia();
        let settle!: () => void;
        jest.spyOn(repository, 'get').mockImplementationOnce(
            () =>
                new Promise((resolve, rejectRequest) => {
                    settle = () => (reject ? rejectRequest(new Error('Late upload')) : resolve(media));
                }),
        );
        const wrapper = await createWrapper(repository);
        const notification = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        wrapper
            .getComponent<ComponentPublicInstance>('sw-upload-listener-stub')
            .vm.$emit('media-upload-finish', { targetId: media.id });
        await flushPromises();
        wrapper.unmount();
        settle();
        await flushPromises();
        expect(getDocuments()).toHaveLength(0);
        expect(notification).not.toHaveBeenCalled();
    });

    it('keeps a pending add upload when an unrelated editor is opened and cancelled', async () => {
        const repository = repositoryFactory.create('media');
        const media = createMedia('new-file');
        let resolveMedia!: (value: Entity<'media'>) => void;
        jest.spyOn(repository, 'get').mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveMedia = resolve;
                }),
        );
        const wrapper = await createWrapper(repository);
        await addMedia(wrapper, [createMedia()]);
        wrapper
            .getComponent<ComponentPublicInstance>('sw-upload-listener-stub')
            .vm.$emit('media-upload-finish', { targetId: media.id });
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await clickButton(wrapper, 'global.default.cancel');
        resolveMedia(media);
        await flushPromises();
        expect(Array.from(getDocuments()!, (document) => document.title)).toEqual(['manual', 'new-file']);
        wrapper.unmount();
    });
});
