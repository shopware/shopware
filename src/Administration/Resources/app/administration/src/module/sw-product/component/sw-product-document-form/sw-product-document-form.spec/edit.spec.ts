/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { ComponentPublicInstance } from 'vue';

import {
    createProduct,
    cleanupWrappers,
    createWrapper,
    createMedia,
    clickButton,
    selectMedia,
    addDocuments,
    getDocuments,
} from './helpers';

describe('sw-product-document-form editing', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        Object.assign(store, { product: createProduct() });
        store.parentProduct = createProduct();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        cleanupWrappers();
    });

    it.each([
        [0, 'click'],
        [1, 'click'],
        [0, 'keydown.enter'],
        [1, 'keydown.enter'],
    ] as const)('opens the editor from document text cell %s using %s', async (cell, event) => {
        const wrapper = await createWrapper();
        await addDocuments(wrapper, [createMedia()]);
        await wrapper.findAll('.sw-product-document-form__edit-link')[cell].trigger(event);

        expect(wrapper.find('.sw-product-document-form__edit-content').exists()).toBe(true);
        expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__name-input input').element.value).toBe('manual');
        await clickButton(wrapper, 'global.default.cancel');
        expect(getDocuments()?.[0].title).toBe('manual');
    });

    it.each(['read-only', 'inherited'])('disables document text links for %s documents', async (state) => {
        const wrapper = await createWrapper();
        await addDocuments(wrapper, [createMedia()]);
        const store = Shopware.Store.get('swProductDetail');
        if (state === 'inherited') {
            store.parentProduct = store.product;
            store.product = createProduct();
        } else {
            Object.assign(global, { activeAclRoles: ['product.viewer'] });
        }
        const readonly = await createWrapper(undefined, undefined, undefined, { isInherited: state === 'inherited' });
        const links = readonly.findAll('.sw-product-document-form__edit-link');
        expect(links).toHaveLength(2);
        for (const link of links) {
            expect(link.attributes('aria-disabled')).toBe('true');
            await link.trigger('click');
            await link.trigger('keydown.enter');
        }
        expect(readonly.find('.sw-product-document-form__edit-content').exists()).toBe(false);
    });

    it('discards a cancelled replacement and preserves the title and position when applying a replacement', async () => {
        const wrapper = await createWrapper();
        const original = createMedia();
        await addDocuments(wrapper, [original]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
        await selectMedia(wrapper, [createMedia('cancelled')]);
        await clickButton(wrapper, 'global.default.cancel');
        expect(getDocuments()?.[0].mediaId).toBe(original.id);

        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
        const replacement = createMedia('replacement');
        await selectMedia(wrapper, [replacement]);
        await clickButton(wrapper, 'global.default.apply');
        expect(getDocuments()?.[0]).toMatchObject({ title: 'manual', position: 0, mediaId: replacement.id });
    });

    it('displays an edited saved title and allows reverting it before saving the product', async () => {
        const wrapper = await createWrapper();
        await addDocuments(wrapper, [createMedia()]);
        const document = getDocuments()![0];
        Object.assign(document, {
            title: 'Original manual',
            translated: { ...document.translated, title: 'Original manual' },
            _isNew: false,
        });
        await flushPromises();

        for (const title of ['Updated manual', 'Original manual']) {
            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', title);
            await clickButton(wrapper, 'global.default.apply');

            expect(document.title).toBe(title);
            expect(wrapper.get('.document-row').text()).toContain(title);

            await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
            expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__name-input input').element.value).toBe(title);
            await clickButton(wrapper, 'global.default.cancel');
        }
    });

    it('shows required-field errors on Apply and clears each error when corrected', async () => {
        const wrapper = await createWrapper();
        const media = createMedia();
        await addDocuments(wrapper, [media]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        expect(wrapper.find('.sw-product-document-form__file-error').exists()).toBe(false);
        wrapper.getComponent<ComponentPublicInstance>('.mt-text-field').vm.$emit('update:modelValue', '   ');
        await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
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
        await addDocuments(wrapper, [media]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
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
        await addDocuments(wrapper, [createMedia('original')]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
        wrapper
            .findAllComponents<ComponentPublicInstance>('sw-upload-listener-stub')[1]
            .vm.$emit('media-upload-finish', { targetId: replacement.id });
        await flushPromises();

        expect(wrapper.get('.sw-media-upload-v2__file-headline').text()).toBe('uploaded-replacement.pdf');
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
        await addDocuments(wrapper, [original]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');
        await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');
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

        expect(wrapper.get('.sw-media-upload-v2__file-headline').text()).toBe('original.pdf');
        await clickButton(wrapper, 'global.default.apply');
        expect(getDocuments()?.[0].mediaId).toBe(original.id);
    });
});
