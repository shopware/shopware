/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { ComponentPublicInstance } from 'vue';

import { createWrapper, createMedia, clickButton, selectMedia, addMedia, getDocuments } from './helpers';

describe('sw-product-document-form editing', () => {
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
    });

    it('applies a replacement while preserving the title and position, and supports cancelling', async () => {
        const wrapper = await createWrapper();
        const original = createMedia();
        await addMedia(wrapper, [original]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-product-document-form__remove-file').trigger('click');
        await selectMedia(wrapper, [createMedia('cancelled')]);
        await clickButton(wrapper, 'global.default.cancel');
        expect(getDocuments()?.[0].mediaId).toBe(original.id);

        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-product-document-form__remove-file').trigger('click');
        const replacement = createMedia('replacement');
        await selectMedia(wrapper, [replacement]);
        await clickButton(wrapper, 'global.default.apply');
        expect(getDocuments()?.[0]).toMatchObject({ title: 'manual', position: 0, mediaId: replacement.id });
    });

    it('shows each required field error on Save and clears it when corrected', async () => {
        const wrapper = await createWrapper();
        const media = createMedia();
        await addMedia(wrapper, [media]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        expect(wrapper.find('.sw-product-document-form__file-error').exists()).toBe(false);
        wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', '   ');
        await wrapper.get('.sw-product-document-form__remove-file').trigger('click');
        expect(wrapper.get('.sw-product-document-form__save-document').attributes('disabled')).toBeUndefined();
        await clickButton(wrapper, 'global.default.apply');
        expect(wrapper.get('.sw-product-document-form__name-input').classes()).toContain('has--error');
        expect(wrapper.get('.sw-product-document-form__file-error').text()).toContain(
            'sw-product.documentForm.errorFileRequired',
        );
        expect(getDocuments()?.[0].title).toBe('manual');
        expect(getDocuments()?.[0].mediaId).toBe(media.id);
        wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', 'Manual');
        await flushPromises();
        expect(wrapper.get('.sw-product-document-form__name-input').classes()).not.toContain('has--error');
        expect(wrapper.find('.sw-product-document-form__file-error').exists()).toBe(true);
        await selectMedia(wrapper, [media]);
        expect(wrapper.find('.sw-product-document-form__file-error').exists()).toBe(false);
        await clickButton(wrapper, 'global.default.apply');
        expect(getDocuments()?.[0].title).toBe('Manual');
    });

    it('clears validation errors when reopening the editor', async () => {
        const wrapper = await createWrapper();
        const media = createMedia();
        await addMedia(wrapper, [media]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-product-document-form__remove-file').trigger('click');
        await clickButton(wrapper, 'global.default.apply');
        expect(wrapper.find('.sw-product-document-form__file-error').exists()).toBe(true);
        await clickButton(wrapper, 'global.default.cancel');
        expect(getDocuments()?.[0].mediaId).toBe(media.id);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        expect(wrapper.find('.sw-product-document-form__file-error').exists()).toBe(false);
        await clickButton(wrapper, 'global.default.cancel');
    });

    it('accepts an uploaded replacement in the active editor session', async () => {
        const replacement = createMedia('uploaded-replacement');
        const repository = repositoryFactory.create('media');
        jest.spyOn(repository, 'get').mockResolvedValue(replacement);
        const wrapper = await createWrapper(repository);
        await addMedia(wrapper, [createMedia('original')]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-product-document-form__remove-file').trigger('click');
        wrapper
            .findAllComponents<ComponentPublicInstance>('sw-upload-listener-stub')[1]
            .vm.$emit('media-upload-finish', { targetId: replacement.id });
        await flushPromises();

        expect(wrapper.get('.sw-product-document-form__file-name').text()).toBe('uploaded-replacement.pdf');
        await clickButton(wrapper, 'global.default.apply');
        expect(getDocuments()?.[0].mediaId).toBe(replacement.id);
    });

    it('discards a late upload after cancelling and reopening the same document', async () => {
        const repository = repositoryFactory.create('media');
        const original = createMedia('original');
        const cancelled = createMedia('cancelled-upload');
        let resolveMedia: (media: Entity<'media'>) => void = () => {};
        jest.spyOn(repository, 'get').mockReturnValue(
            new Promise((resolve) => {
                resolveMedia = resolve;
            }),
        );
        const wrapper = await createWrapper(repository);
        await addMedia(wrapper, [original]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-product-document-form__remove-file').trigger('click');
        const uploadListener = wrapper.findAllComponents<ComponentPublicInstance>('sw-upload-listener-stub')[1];
        const cancelledUploadTag = uploadListener.attributes('upload-tag');
        uploadListener.vm.$emit('media-upload-finish', { targetId: cancelled.id });

        await clickButton(wrapper, 'global.default.cancel');
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        expect(wrapper.findAllComponents('sw-upload-listener-stub')[1].attributes('upload-tag')).not.toBe(
            cancelledUploadTag,
        );
        resolveMedia(cancelled);
        await flushPromises();

        expect(wrapper.get('.sw-product-document-form__file-name').text()).toBe('original.pdf');
        await clickButton(wrapper, 'global.default.apply');
        expect(getDocuments()?.[0].mediaId).toBe(original.id);
    });
});
