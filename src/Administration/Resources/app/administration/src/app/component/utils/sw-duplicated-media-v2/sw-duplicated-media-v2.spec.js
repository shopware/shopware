/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const uploadTaskMock = {
    running: false,
    src: File,
    uploadTag: 'upload-tag-sw-media-index',
    targetId: 'aaaef50651e04f59bbc9c309b5110e23',
    fileName: 'my-demo-image',
    extension: 'jpg',
    error: null,
    successAmount: 0,
    failureAmount: 1,
    totalAmount: 1,
};

describe('components/utils/sw-duplicated-media-v2', () => {
    let wrapper;
    let uploads = {};
    let mediaRepository;
    let uploadEventListener;

    beforeEach(async () => {
        uploads = {};
        uploadEventListener = jest.fn();
        mediaRepository = {
            search: () => Promise.resolve([{ id: 'foo' }]),
            get: jest.fn(() =>
                Promise.resolve({
                    id: 'foo',
                    hasFile: true,
                }),
            ),
            delete: jest.fn(() => Promise.resolve()),
        };
        wrapper = mount(await wrapTestComponent('sw-duplicated-media-v2', { sync: true }), {
            global: {
                provide: {
                    shortcutService: {
                        startEventListener() {},
                        stopEventListener() {},
                    },
                    repositoryFactory: {
                        create: () => mediaRepository,
                    },
                    mediaPresignedUploadService: {
                        uploadFile: jest.fn(),
                    },
                    mediaService: {
                        addDefaultListener: jest.fn(),
                        removeDefaultListener: jest.fn(),
                        runUploads: jest.fn(),
                        addUpload: (tag, uploadTask) => {
                            if (!uploads[tag]) uploads[tag] = [];

                            uploads[tag].push(uploadTask);
                        },
                        provideName: async (fileName) => {
                            return { fileName: `${fileName}_(2)` };
                        },
                        keepFile: jest.fn(),
                        cancelUpload: jest.fn(),
                        getListenerForTag: () => [uploadEventListener],
                        _createUploadEvent: (action, uploadTag, payload) => ({ action, uploadTag, payload }),
                    },
                },
                stubs: {
                    'sw-modal': {
                        template: `
                            <div class="sw-modal">
                                <slot name="modal-header">
                                    <slot name="modal-title"></slot>
                                </slot>
                                <slot name="modal-body">
                                     <slot></slot>
                                </slot>
                                <slot name="modal-footer">
                                </slot>
                            </div>
                        `,
                    },
                    'sw-container': true,
                    'sw-media-preview-v2': true,
                    'sw-radio-field': await wrapTestComponent('sw-radio-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-media-media-item': true,
                    'sw-checkbox-field': true,
                    'router-link': true,
                    'sw-loader': true,
                    'sw-help-text': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                },
            },
        });
    });

    it('should upload the renamed file', async () => {
        await wrapper.vm.renameFile(uploadTaskMock);

        const matchingUploadTask = uploads[uploadTaskMock.uploadTag].find((upload) => {
            return upload.targetId === uploadTaskMock.targetId;
        });

        expect(matchingUploadTask.fileName).toBe(`${uploadTaskMock.fileName}_(2)`);
        expect(wrapper.vm.mediaService.runUploads).toHaveBeenCalledWith('upload-tag-sw-media-index');
    });

    it('should keep the existing file', async () => {
        wrapper.vm.defaultOption = 'Keep';
        await wrapper.setData({ failedUploadTasks: [uploadTaskMock] });

        await wrapper.vm.solveDuplicate();
        await wrapper.vm.$nextTick();

        const expectedTask = { ...uploadTaskMock, ...{ targetId: 'foo' } };

        expect(wrapper.vm.mediaService.keepFile).toHaveBeenCalledWith(expectedTask.uploadTag, expectedTask);
    });

    it('should replace the file on the server with the local file', async () => {
        wrapper.vm.defaultOption = 'Replace';
        await wrapper.setData({ failedUploadTasks: [uploadTaskMock] });
        await flushPromises();

        const radio = wrapper.find('input[type="radio"]');
        await radio.setValue('checked');

        const replaceButton = wrapper.find('.sw-duplicated-media-v2__upload');
        await replaceButton.trigger('click');

        expect(wrapper.vm.mediaService.runUploads).toHaveBeenCalledWith('upload-tag-sw-media-index');
    });

    it('should upload a renamed file through a new presigned upload and report the confirmed media id', async () => {
        Shopware.Store.get('context').app.config = { settings: { presignedUploadSupported: true } };
        const presignedUploadService = wrapper.vm.mediaPresignedUploadService;
        presignedUploadService.uploadFile.mockResolvedValue('confirmed-id');
        const file = new File(['content'], 'my-demo-image.jpg', { type: 'image/jpeg' });

        await wrapper.vm.renameFile({ ...uploadTaskMock, src: file });

        expect(presignedUploadService.uploadFile).toHaveBeenCalledWith(file, {
            fileName: 'my-demo-image_(2).jpg',
            id: null,
        });
        expect(uploadEventListener).toHaveBeenCalledWith(
            expect.objectContaining({
                action: 'media-upload-finish',
                payload: expect.objectContaining({ targetId: 'confirmed-id' }),
            }),
        );
        expect(wrapper.vm.mediaService.runUploads).not.toHaveBeenCalled();
    });

    it('should skip a file without deleting anything when no placeholder media exists', async () => {
        mediaRepository.get.mockResolvedValue(null);
        await wrapper.setData({ failedUploadTasks: [uploadTaskMock] });

        await wrapper.vm.skipCurrentFile();

        expect(mediaRepository.delete).not.toHaveBeenCalled();
        expect(wrapper.vm.mediaService.cancelUpload).toHaveBeenCalledWith(uploadTaskMock.uploadTag, uploadTaskMock);
    });

    it('should delete the empty placeholder media when a file is skipped', async () => {
        mediaRepository.get.mockResolvedValue({ id: 'placeholder-id', hasFile: false });
        await wrapper.setData({ failedUploadTasks: [uploadTaskMock] });

        await wrapper.vm.skipCurrentFile();

        expect(mediaRepository.delete).toHaveBeenCalledWith('placeholder-id', expect.anything());
    });

    it('should keep the existing file without deleting anything when no placeholder media exists', async () => {
        mediaRepository.get.mockResolvedValue(null);

        await wrapper.vm.keepFile({ ...uploadTaskMock });

        expect(mediaRepository.delete).not.toHaveBeenCalled();
        expect(wrapper.vm.mediaService.keepFile).toHaveBeenCalledWith(
            uploadTaskMock.uploadTag,
            expect.objectContaining({ targetId: 'foo', originalTargetId: uploadTaskMock.targetId }),
        );
    });

    it('should delete the empty placeholder media when the existing file is kept', async () => {
        mediaRepository.get.mockResolvedValue({ id: 'placeholder-id', hasFile: false });

        await wrapper.vm.keepFile({ ...uploadTaskMock });

        expect(mediaRepository.delete).toHaveBeenCalledWith('placeholder-id', expect.anything());
    });

    it('should upload a replacement under the task file name and return the confirmed media id', async () => {
        const presignedUploadService = wrapper.vm.mediaPresignedUploadService;
        presignedUploadService.uploadFile.mockResolvedValue('existing-id');

        const confirmedMediaId = await wrapper.vm.presignedUpload(uploadTaskMock, 'existing-id');

        expect(presignedUploadService.uploadFile).toHaveBeenCalledWith(uploadTaskMock.src, {
            fileName: 'my-demo-image.jpg',
            id: 'existing-id',
        });
        expect(confirmedMediaId).toBe('existing-id');
    });
});
