/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import { cleanupWrappers, createWrapper, createMedia, addMedia, getDocuments } from './helpers';

describe('sw-product-document-form downloads', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    const createObjectURLDescriptor = Object.getOwnPropertyDescriptor(URL, 'createObjectURL');
    const revokeObjectURLDescriptor = Object.getOwnPropertyDescriptor(URL, 'revokeObjectURL');
    let link: HTMLAnchorElement;
    let dispatchSpy: jest.SpyInstance;
    const mediaService = { prepareDownloadMedia: jest.fn(), downloadMedia: jest.fn() };
    const createObjectURLMock = jest.fn();
    const revokeObjectURLMock = jest.fn();

    beforeEach(() => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = repositoryFactory.create('product').create();
        store.parentProduct = repositoryFactory.create('product').create();
        Object.assign(global, { activeAclRoles: ['product.editor', 'media.viewer'] });
        link = document.createElement('a');
        dispatchSpy = jest.spyOn(link, 'dispatchEvent').mockReturnValue(true);
        createObjectURLMock.mockReset().mockReturnValue('blob:document-download');
        revokeObjectURLMock.mockReset();
        const createElement = document.createElement.bind(document);
        jest.spyOn(document, 'createElement').mockImplementation((tagName, options) =>
            tagName === 'a' ? link : createElement(tagName, options),
        );
        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: createObjectURLMock,
        });
        Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, value: revokeObjectURLMock });
        mediaService.prepareDownloadMedia.mockReset();
        mediaService.downloadMedia.mockReset();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        jest.restoreAllMocks();
        if (createObjectURLDescriptor) {
            Object.defineProperty(URL, 'createObjectURL', createObjectURLDescriptor);
        } else {
            Reflect.deleteProperty(URL, 'createObjectURL');
        }
        if (revokeObjectURLDescriptor) {
            Object.defineProperty(URL, 'revokeObjectURL', revokeObjectURLDescriptor);
        } else {
            Reflect.deleteProperty(URL, 'revokeObjectURL');
        }
        cleanupWrappers();
    });

    it('downloads a file response with the original file name and releases the object URL', async () => {
        const file = new Blob(['manual']);
        mediaService.prepareDownloadMedia.mockResolvedValue({ type: 'blob' });
        mediaService.downloadMedia.mockResolvedValue(file);
        const wrapper = await createWrapper(undefined, mediaService);
        const media = createMedia('operating-manual');
        await addMedia(wrapper, [media]);
        getDocuments()![0].title = 'Translated document name';

        await wrapper.get('.sw-product-document-form__download').trigger('click');
        await flushPromises();

        expect(mediaService.prepareDownloadMedia).toHaveBeenCalledWith(media.id);
        expect(mediaService.downloadMedia).toHaveBeenCalledWith(media.id);
        expect(createObjectURLMock).toHaveBeenCalledWith(file);
        expect(link.href).toBe('blob:document-download');
        expect(link.download).toBe('operating-manual.pdf');
        expect(dispatchSpy).toHaveBeenCalledWith(expect.any(MouseEvent));
        expect(revokeObjectURLMock).toHaveBeenCalledWith('blob:document-download');
    });

    it('opens an external download without fetching a file response', async () => {
        mediaService.prepareDownloadMedia.mockResolvedValue({
            type: 'external',
            url: 'https://cdn.example.test/manual',
        });
        const wrapper = await createWrapper(undefined, mediaService);
        await addMedia(wrapper, [createMedia()]);

        await wrapper.get('.sw-product-document-form__download').trigger('click');
        await flushPromises();

        expect(link.href).toBe('https://cdn.example.test/manual');
        expect(link.target).toBe('_blank');
        expect(link.rel).toBe('noopener noreferrer');
        expect(dispatchSpy).toHaveBeenCalled();
        expect(mediaService.downloadMedia).not.toHaveBeenCalled();
        expect(createObjectURLMock).not.toHaveBeenCalled();
    });

    it.each(['prepare', 'download'])('shows an error when %s fails', async (step) => {
        mediaService.prepareDownloadMedia.mockResolvedValue({ type: 'blob' });
        if (step === 'prepare') {
            mediaService.prepareDownloadMedia.mockRejectedValue(new Error('Download failed'));
        } else {
            mediaService.downloadMedia.mockRejectedValue(new Error('Download failed'));
        }
        const wrapper = await createWrapper(undefined, mediaService);
        await addMedia(wrapper, [createMedia()]);
        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        await wrapper.get('.sw-product-document-form__download').trigger('click');
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'global.sw-media-media-item.notification.downloadError.message',
            }),
        );
        expect(dispatchSpy).not.toHaveBeenCalled();
    });

    it('does not request a download without media viewer permission', async () => {
        const wrapper = await createWrapper(undefined, mediaService);
        await addMedia(wrapper, [createMedia()]);
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        await wrapper.get('.sw-product-document-form__download').trigger('click');
        await flushPromises();

        expect(mediaService.prepareDownloadMedia).not.toHaveBeenCalled();
        expect(dispatchSpy).not.toHaveBeenCalled();
    });

    it('allows a viewer to download an inherited document without creating an override', async () => {
        Object.assign(global, { activeAclRoles: ['product.viewer', 'media.viewer'] });
        const store = Shopware.Store.get('swProductDetail');
        store.product.parentId = store.parentProduct.id;
        const media = createMedia('parent-manual');
        const document = repositoryFactory.create('product_document').create();
        Object.assign(document, { mediaId: media.id, translations: [{ mediaId: media.id, media }] });
        store.parentProduct.documents?.add(document);
        mediaService.prepareDownloadMedia.mockResolvedValue({
            type: 'external',
            url: 'https://cdn.example.test/manual',
        });
        const wrapper = await createWrapper(undefined, mediaService, undefined, { isInherited: true });

        expect(wrapper.get('.sw-product-document-form__download').attributes('disabled')).toBe('false');
        await wrapper.get('.sw-product-document-form__download').trigger('click');
        await flushPromises();

        expect(mediaService.prepareDownloadMedia).toHaveBeenCalledWith(media.id);
        expect(dispatchSpy).toHaveBeenCalled();
        expect(getDocuments()).toHaveLength(0);
        expect(store.parentProduct.documents).toHaveLength(1);
    });
});
