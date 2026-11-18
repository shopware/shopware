/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

const mediaService = {
    runUploads: jest.fn().mockResolvedValue(),
    renameMedia: jest.fn().mockResolvedValue(),
};

const createWrapper = async (mediaPresignedUploadService = {}) => {
    return mount(await wrapTestComponent('sw-media-modal-replace', { sync: true }), {
        props: {
            itemToReplace: {
                id: 'media-id-123',
                fileName: 'image',
                fileExtension: 'png',
                isLoading: false,
            },
        },
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div><slot /><slot name="modal-footer" /></div>',
                },
                'sw-media-upload-v2': true,
                'sw-media-replace': true,
                'sw-upload-listener': true,
                'mt-button': true,
            },
            provide: {
                mediaService,
                mediaPresignedUploadService,
                repositoryFactory: {
                    create: jest.fn(),
                },
            },
        },
    });
};

describe('components/media/sw-media-modal-replace', () => {
    beforeEach(() => {
        Shopware.Store.get('context').app.config = {
            settings: { presignedUploadSupported: false },
        };
    });

    it('uploads via mediaService and renames back to the original filename', async () => {
        jest.spyOn(Shopware.Utils, 'createId').mockReturnValue('random-conflict-free-id');
        const wrapper = await createWrapper();

        const uploadData = [{ fileName: 'shopware', extension: 'png', src: 'blob:...' }];
        wrapper.vm.onNewUpload({ data: uploadData });

        expect(uploadData[0].fileName).toBe('random-conflict-free-id');

        await wrapper.vm.replaceMediaItem();

        expect(mediaService.runUploads).toHaveBeenCalledWith('media-id-123');
        expect(mediaService.renameMedia).toHaveBeenCalledWith('media-id-123', 'image');
    });

    it('sends the size of the new file with a presigned replacement', async () => {
        Shopware.Store.get('context').app.config.settings.presignedUploadSupported = true;
        const mediaPresignedUploadService = {
            prepareUpload: jest
                .fn()
                .mockResolvedValue({ url: 'https://s3.example.com/presigned', path: 'media/ab/cd/manual.png' }),
            getImageDimensions: jest.fn().mockResolvedValue(null),
            uploadToPresignedUrl: jest.fn().mockResolvedValue(),
            finalizeUpload: jest.fn().mockResolvedValue(),
        };
        const wrapper = await createWrapper(mediaPresignedUploadService);

        const file = new File(['12345'], 'manual.png', { type: 'image/png' });
        await wrapper.findComponent({ name: 'sw-upload-listener' }).vm.$emit('media-upload-add', {
            data: [{ fileName: 'manual', extension: 'png', src: file }],
        });
        await wrapper.findComponent('.sw-media-replace__replace-media-action').vm.$emit('click');
        await flushPromises();

        expect(mediaPresignedUploadService.prepareUpload).toHaveBeenCalledWith(
            expect.objectContaining({ mediaId: 'media-id-123', fileSize: 5 }),
        );
    });
});
