/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { ComponentPublicInstance } from 'vue';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import fileValidationService from 'src/app/service/file-validation.service';

import {
    createProduct,
    cleanupWrappers,
    createWrapper,
    createMedia,
    addMedia,
    clickButton,
    getDocuments,
} from './sw-product-document-form.spec/helpers';

describe('sw-product-document-form', () => {
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

    it('adds an assignment to the product save payload without copying the media file', async () => {
        const wrapper = await createWrapper();
        const media = createMedia();
        await addMedia(wrapper, [media]);

        expect(wrapper.findAll('.document-row')).toHaveLength(1);
        expect(wrapper.text()).toContain('manual');
        const payload = new ChangesetGenerator().generate(Shopware.Store.get('swProductDetail').product);
        expect(payload).toMatchObject({
            changes: { documents: [expect.objectContaining({ mediaId: media.id, title: 'manual', position: 0 })] },
        });
        expect(JSON.stringify(payload)).not.toContain('"media":');
    });

    it.each([
        ['manual.pdf', 'application/pdf'],
        ['manual.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ['manual.jpg', 'image/jpeg'],
        ['manual.jpeg', 'image/jpeg'],
        ['manual.png', 'image/png'],
    ])('accepts %s through the media modal upload validation', async (name, type) => {
        const wrapper = await createWrapper();
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');
        wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2').vm.$emit('media-upload-sidebar-open');
        await flushPromises();

        const accept = wrapper.getComponent('sw-media-modal-v2-stub').attributes('file-accept')!;
        const { checkByType } = fileValidationService();
        expect(checkByType(new File(['document'], name, { type }), accept)).toBe(true);
        expect(checkByType(new File(['video'], 'video.mp4', { type: 'video/mp4' }), accept)).toBe(false);
    });

    it('rejects duplicate and private media', async () => {
        const wrapper = await createWrapper();
        const media = createMedia();
        const privateMedia = createMedia('private');
        privateMedia.private = true;
        await addMedia(wrapper, [media, media, privateMedia]);
        expect(wrapper.findAll('.document-row')).toHaveLength(1);
    });

    it('renders the media association loaded through translations', async () => {
        const media = createMedia('reloaded');
        const document = repositoryFactory.create('product_document').create();
        Object.assign(document, {
            title: 'Original manual',
            translated: { title: 'Translated manual' },
            mediaId: media.id,
            translations: [{ mediaId: media.id, media }],
        });
        getDocuments()?.add(document);
        const wrapper = await createWrapper();
        expect(wrapper.text()).toContain('Translated manual');
        expect(wrapper.text()).toContain('reloaded.pdf');
        expect(wrapper.text()).toContain('1.00KB');
    });

    it('disables document management for a product viewer', async () => {
        Object.assign(global, { activeAclRoles: ['product.viewer'] });
        const wrapper = await createWrapper();
        expect(wrapper.get('.sw-product-document-form__empty-state button').attributes('disabled')).toBeDefined();
    });

    it('requires new documents to be created in the default language', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        const wrapper = await createWrapper();
        await flushPromises();
        expect(wrapper.find('.sw-product-document-form__empty-state .mt-button').exists()).toBe(false);
        expect(wrapper.find('.sw-product-document-form__language-hint').exists()).toBe(false);
    });

    it('accepts the tenth document and rejects additional media from the same selection', async () => {
        const wrapper = await createWrapper();
        await addMedia(
            wrapper,
            Array.from({ length: 9 }, (_, index) => createMedia(`manual-${index}`)),
        );
        const lastMedia = createMedia('tenth');
        const excessMedia = createMedia('eleventh');
        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        await addMedia(wrapper, [lastMedia, excessMedia, createMedia('twelfth')]);

        expect(wrapper.findAll('.document-row')).toHaveLength(10);
        expect(getDocuments()?.[9].mediaId).toBe(lastMedia.id);
        expect(getDocuments()?.some((document) => document.mediaId === excessMedia.id)).toBe(false);
        expect(wrapper.get('.sw-product-document-form__add-button button').attributes('disabled')).toBeDefined();
        expect(notificationSpy).toHaveBeenCalledTimes(1);
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'error', message: 'sw-product.documentForm.errorDocumentLimit' }),
        );
    });

    it('rejects a completed upload when the draft already contains ten documents', async () => {
        const media = createMedia('uploaded eleventh');
        const repository = repositoryFactory.create('media');
        jest.spyOn(repository, 'get').mockResolvedValue(media);
        const wrapper = await createWrapper(repository);
        await addMedia(
            wrapper,
            Array.from({ length: 10 }, (_, index) => createMedia(`manual-${index}`)),
        );
        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        wrapper
            .getComponent<ComponentPublicInstance>('sw-upload-listener-stub')
            .vm.$emit('media-upload-finish', { targetId: media.id });
        await flushPromises();

        expect(getDocuments()).toHaveLength(10);
        expect(getDocuments()?.some((document) => document.mediaId === media.id)).toBe(false);
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'error', message: 'sw-product.documentForm.errorDocumentLimit' }),
        );
    });

    it('accepts a completed upload as the tenth document and disables further uploads', async () => {
        const media = createMedia('uploaded manual');
        const repository = repositoryFactory.create('media');
        jest.spyOn(repository, 'get').mockResolvedValue(media);
        const wrapper = await createWrapper(repository);
        await addMedia(
            wrapper,
            Array.from({ length: 9 }, (_, index) => createMedia(`manual-${index}`)),
        );
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');

        wrapper
            .getComponent<ComponentPublicInstance>('sw-upload-listener-stub')
            .vm.$emit('media-upload-finish', { targetId: media.id });
        await flushPromises();

        expect(wrapper.findAll('.document-row')).toHaveLength(10);
        expect(wrapper.text()).toContain('uploaded manual');
        expect(getDocuments()?.[9].mediaId).toBe(media.id);
        expect(wrapper.get<HTMLButtonElement>('.sw-media-upload-v2__button.upload').element.disabled).toBe(true);
    });

    it('does not attach a late upload to the next product after navigation', async () => {
        const media = createMedia();
        const repository = repositoryFactory.create('media');
        let resolveMedia: (value: Entity<'media'>) => void = () => {};
        jest.spyOn(repository, 'get').mockReturnValue(
            new Promise((resolve) => {
                resolveMedia = resolve;
            }),
        );
        const wrapper = await createWrapper(repository);

        wrapper
            .getComponent<ComponentPublicInstance>('sw-upload-listener-stub')
            .vm.$emit('media-upload-finish', { targetId: media.id });
        Object.assign(Shopware.Store.get('swProductDetail'), { product: createProduct() });
        resolveMedia(media);
        await flushPromises();

        expect(wrapper.findAll('.document-row')).toHaveLength(0);
        expect(getDocuments()).toHaveLength(0);
    });
    it.each(['unsupported extension', 'oversized file', 'unknown size'])(
        'rejects a media selection with %s',
        async (failure) => {
            const wrapper = await createWrapper();
            const media = createMedia();
            if (failure === 'unsupported extension') {
                media.fileExtension = 'exe';
            } else if (failure === 'oversized file') {
                media.fileSize = 20 * 1024 * 1024 + 1;
            } else {
                media.fileSize = undefined;
            }
            await addMedia(wrapper, [media]);
            expect(wrapper.findAll('.document-row')).toHaveLength(0);
        },
    );
});
