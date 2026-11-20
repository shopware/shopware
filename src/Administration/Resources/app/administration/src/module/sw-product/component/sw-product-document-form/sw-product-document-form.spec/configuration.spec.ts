/**
 * @sw-package inventory
 */
import type { ComponentPublicInstance } from 'vue';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import {
    addDocuments,
    cleanupWrappers,
    clickButton,
    createMedia,
    createProduct,
    createWrapper,
    getDocuments,
    selectMedia,
    uploadSettings,
} from './helpers';

describe('sw-product-document-form upload settings', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    const { maxFileSize } = uploadSettings;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
    });

    afterEach(async () => {
        cleanupWrappers();
        await flushPromises();
        Shopware.Context.api.languageId = originalLanguageId;
        jest.restoreAllMocks();
    });

    it('passes the upload settings to the upload picker and the media modal', async () => {
        const wrapper = await createWrapper();
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');
        const upload = wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2');

        expect(upload.props()).toMatchObject({
            extensionAccept: 'pdf,docx,jpg,jpeg,png',
            extensionMimeTypesByExtension: uploadSettings.allowedMimeTypesByExtension,
            maxFileSize,
        });

        upload.vm.$emit('media-upload-sidebar-open');
        await flushPromises();

        expect(wrapper.get('sw-media-modal-v2-stub').attributes('file-accept')?.split(',')).toEqual(
            expect.arrayContaining([
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg',
                'image/png',
            ]),
        );
    });

    it.each([
        { name: 'manual.pdf', type: 'application/pdf', size: maxFileSize, accepted: true },
        { name: 'manual.pdf', type: '', size: 1024, accepted: true },
        { name: 'too-large.pdf', type: 'application/pdf', size: maxFileSize + 1, accepted: false },
        { name: 'manual.txt', type: 'application/pdf', size: 1024, accepted: false },
    ])('checks $name (MIME "$type", $size bytes) before uploading', async ({ name, type, size, accepted }) => {
        const mediaRepository = repositoryFactory.create('media');
        jest.spyOn(mediaRepository, 'sync').mockResolvedValue(undefined);
        jest.spyOn(mediaRepository, 'search').mockResolvedValue(
            new Shopware.Data.EntityCollection('/media', 'media', Shopware.Context.api),
        );
        const mediaService = {
            prepareDownloadMedia: jest.fn(),
            downloadMedia: jest.fn(),
            addUploads: jest.fn().mockResolvedValue(undefined),
        };
        const wrapper = await createWrapper(mediaRepository, mediaService);
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');
        const file = new File([new Uint8Array(size)], name, { type });
        const input = wrapper.get<HTMLInputElement>('input[type="file"]');
        Object.defineProperty(input.element, 'files', { value: [file] });
        await input.trigger('change');
        await flushPromises();

        const expectedUpload: unknown = expect.objectContaining({ src: file });
        expect(mediaService.addUploads).toHaveBeenCalledWith(expect.any(String), accepted ? [expectedUpload] : []);
    });

    it.each([
        { extension: 'pdf', size: maxFileSize, accepted: true },
        { extension: 'pdf', size: maxFileSize + 1, accepted: false },
        { extension: 'docx', size: 1024, accepted: true },
        { extension: 'jpg', size: 1024, accepted: true },
        { extension: 'jpeg', size: 1024, accepted: true },
        { extension: 'png', size: 1024, accepted: true },
        { extension: 'txt', size: 1024, accepted: false },
    ])('validates $extension files of $size bytes against the upload settings', async ({ extension, size, accepted }) => {
        const wrapper = await createWrapper();
        const media = createMedia('document');
        media.fileExtension = extension;
        media.fileSize = size;

        await addDocuments(wrapper, [media]);

        expect(getDocuments()).toHaveLength(accepted ? 1 : 0);
        expect(wrapper.findAll('.document-row')).toHaveLength(accepted ? 1 : 0);
    });

    it('uses the upload settings for replacement uploads and media selection', async () => {
        const wrapper = await createWrapper();
        const media = createMedia('original');
        await addDocuments(wrapper, [media]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');

        const upload = wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2');
        expect(upload.props()).toMatchObject({
            extensionAccept: 'pdf,docx,jpg,jpeg,png',
            extensionMimeTypesByExtension: uploadSettings.allowedMimeTypesByExtension,
            maxFileSize,
        });

        const rejected = createMedia('too-large');
        rejected.fileSize = maxFileSize + 1;
        await selectMedia(wrapper, [rejected]);
        expect(getDocuments()?.[0].mediaId).toBe(media.id);
        expect(wrapper.text()).toContain('original.pdf');

        const accepted = createMedia('replacement');
        accepted.fileSize = maxFileSize;
        await selectMedia(wrapper, [accepted]);
        await clickButton(wrapper, 'global.default.apply');
        await flushPromises();
        expect(getDocuments()?.[0].mediaId).toBe(accepted.id);
        expect(wrapper.get('.document-row').text()).toContain('replacement.pdf');
    });

    it('accepts only the file types and size that the API returns', async () => {
        const wrapper = await createWrapper(undefined, undefined, undefined, {
            getUploadSettings: jest.fn().mockResolvedValue({
                allowedExtensions: ['txt'],
                allowedMimeTypesByExtension: { txt: ['text/plain'] },
                maxFileSize: 512,
            }),
        });
        const accepted = createMedia('text-file');
        accepted.fileExtension = 'txt';
        accepted.fileSize = 512;
        const rejected = createMedia('manual');
        await addDocuments(wrapper, [accepted, rejected]);

        expect(getDocuments()).toHaveLength(1);
        expect(getDocuments()?.[0].mediaId).toBe(accepted.id);
        expect(wrapper.get('.document-row').text()).toContain('text-file.txt');
    });

    it('skips the browser-side check when the upload settings cannot be loaded', async () => {
        const wrapper = await createWrapper(undefined, undefined, undefined, {
            getUploadSettings: jest.fn().mockRejectedValue(new Error('Request failed')),
        });
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');

        expect(wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2').props()).toMatchObject({
            extensionAccept: '',
            maxFileSize: Number.POSITIVE_INFINITY,
        });

        const textFile = createMedia('text-file');
        textFile.fileExtension = 'txt';
        const largeFile = createMedia('large');
        largeFile.fileSize = maxFileSize + 1;
        await selectMedia(wrapper, [textFile, largeFile]);

        expect(getDocuments()).toHaveLength(2);
    });
});
