/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import { UploadEvents } from 'src/core/service/api/media.api.service';

const mockSnackbarItem = {
    id: 'media-upload-status',
    message: 'Upload message',
    variant: 'progress',
    progressPercentage: 0,
    duration: 0,
};

const mockSnackbar = {
    addSnackbar: jest.fn(() => mockSnackbarItem),
    removeSnackbar: jest.fn(),
    snackbars: [],
};

const snippetData = {
    'global.sw-media-upload.snackbar.message': (params) => {
        if (params.count === 1) {
            return 'Uploading file';
        }

        return `Uploading files (${params.processed}/${params.total})`;
    },
    'global.sw-media-upload.snackbar.errorMessage': (params) => `${params.count} upload(s) failed`,
    'global.sw-media-upload.notification.illegalFilename.message': (params) => `Illegal filename: ${params.fileName}`,
    'global.sw-media-upload.notification.illegalFileUrl.message': (params) => `Illegal file URL: ${params.fileName}`,
    'global.sw-media-upload.notification.fileTypeNotSupported.message': (params) =>
        `File type not supported: ${params.fileName}`,
    'global.sw-media-upload.notification.requestCanceled.message': (params) => `Request canceled: ${params.fileName}`,
    'global.sw-media-upload.notification.payloadTooLarge.message': (params) => `Payload too large: ${params.fileName}`,
    'global.sw-media-upload.notification.transportError.message': (params) => `Transport error: ${params.fileName}`,
};

jest.mock('@shopware-ag/meteor-component-library', () => ({
    useSnackbar: () => mockSnackbar,
}));

function createFile(name = 'test.jpg', content = 'content', type = 'image/jpeg') {
    return new File([content], name, { type });
}

function createUploadTask(targetId, file) {
    return {
        targetId,
        src: file,
    };
}

function createError(code, detail = 'Error detail') {
    return {
        response: {
            data: {
                errors: [
                    {
                        code,
                        status: '400',
                        title: 'Bad Request',
                        detail,
                    },
                ],
            },
        },
    };
}

function createClientError(code) {
    return {
        code,
    };
}

function createUploadAddedEvent(tasks, uploadTag = 'test-tag') {
    return {
        action: UploadEvents.UPLOAD_ADDED,
        payload: {
            data: tasks,
        },
        uploadTag,
    };
}

function createUploadFinishedEvent(targetId, uploadTag = 'test-tag') {
    return {
        action: UploadEvents.UPLOAD_FINISHED,
        payload: {
            targetId,
        },
        uploadTag,
    };
}

function createUploadFinishedEventWithOriginal(targetId, originalTargetId, uploadTag = 'test-tag') {
    return {
        action: UploadEvents.UPLOAD_FINISHED,
        payload: {
            targetId,
            originalTargetId,
        },
        uploadTag,
    };
}

function createUploadProgressEvent(targetId, loaded, total, uploadTag = 'test-tag') {
    return {
        action: UploadEvents.UPLOAD_PROGRESS,
        payload: {
            targetId,
            loaded,
            total,
        },
        uploadTag,
    };
}

function createUploadFailedEvent(targetId, fileName, error, uploadTag = 'test-tag') {
    return {
        action: UploadEvents.UPLOAD_FAILED,
        payload: {
            targetId,
            fileName,
            error,
        },
        uploadTag,
    };
}

function createUploadCanceledEvent(targetId, uploadTag = 'test-tag') {
    return {
        action: UploadEvents.UPLOAD_CANCELED,
        payload: {
            data: {
                targetId,
            },
        },
        uploadTag,
    };
}

async function createWrapper() {
    const component = await import('src/app/component/utils/sw-upload-status');

    const translate = jest.fn((key, params = {}) => {
        const snippet = snippetData[key];

        return snippet ? snippet(params) : key;
    });

    return mount(component.default, {
        global: {
            provide: {
                mediaService: {
                    addDefaultListener: jest.fn(),
                },
            },
            mixin: [
                {
                    methods: {
                        createNotificationError: jest.fn(),
                    },
                },
            ],
            mocks: {
                $t: translate,
            },
            stubs: {},
        },
    });
}

describe('src/app/component/utils/sw-upload-status', () => {
    let wrapper;

    beforeEach(async () => {
        jest.clearAllMocks();
        mockSnackbar.addSnackbar.mockReturnValue(mockSnackbarItem);
        wrapper = await createWrapper();
    });

    it('should add upload when UPLOAD_ADDED event is triggered', async () => {
        const file = createFile();
        const event = createUploadAddedEvent([createUploadTask('target-123', file)]);

        wrapper.vm.onUploadEvent(event);

        expect(wrapper.vm.uploads.size).toBe(1);
        expect(wrapper.vm.uploadCount).toBe(1);
        expect(mockSnackbar.addSnackbar).toHaveBeenCalled();
    });

    it('should mark upload as finished when UPLOAD_FINISHED event is triggered', async () => {
        wrapper.vm.updateSnackbar = jest.fn();
        const file1 = createFile('test.jpg', 'content');
        const file2 = createFile('test2.jpg', 'content2');

        const tasks = [
            createUploadTask('target-123', file1),
            createUploadTask('target-456', file2),
        ];
        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));
        wrapper.vm.onUploadEvent(createUploadFinishedEvent('target-123'));

        const uploadId = wrapper.vm.getUploadId(file1.name, file1.size);
        const fileInfo = wrapper.vm.uploads.get(uploadId);

        expect(fileInfo.status).toBe('finished');
    });

    it('should mark upload as finished when original target id is provided', async () => {
        wrapper.vm.updateSnackbar = jest.fn();
        const file = createFile('test.jpg', 'content');
        const task = [createUploadTask('original-target', file)];

        wrapper.vm.onUploadEvent(createUploadAddedEvent(task));
        wrapper.vm.onUploadEvent(createUploadFinishedEventWithOriginal('existing-target', 'original-target'));

        const uploadId = wrapper.vm.getUploadId(file.name, file.size);
        const fileInfo = wrapper.vm.uploads.get(uploadId);

        expect(fileInfo.status).toBe('finished');
    });
    it('should mark upload as failed when UPLOAD_FAILED event is triggered', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        const file1 = createFile('test.jpg', 'content');
        const file2 = createFile('test2.jpg', 'content2');

        const tasks = [
            createUploadTask('target-123', file1),
            createUploadTask('target-456', file2),
        ];
        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));

        const error = createError('CONTENT__MEDIA_ILLEGAL_FILE_NAME', 'Illegal filename');
        wrapper.vm.onUploadEvent(createUploadFailedEvent('target-123', 'test.jpg', error));

        const uploadId = wrapper.vm.getUploadId(file1.name, file1.size);
        const fileInfo = wrapper.vm.uploads.get(uploadId);

        expect(fileInfo.status).toBe('failed');
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Illegal filename: test.jpg',
        });
    });

    it('should mark upload as pending when duplicate filename error occurs', async () => {
        const file = createFile();
        const tasks = [createUploadTask('target-123', file)];

        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));

        const error = createError('CONTENT__MEDIA_DUPLICATED_FILE_NAME', 'Duplicated filename');
        wrapper.vm.onUploadEvent(createUploadFailedEvent('target-123', 'test.jpg', error));

        const uploadId = wrapper.vm.getUploadId(file.name, file.size);
        const fileInfo = wrapper.vm.uploads.get(uploadId);

        expect(fileInfo.status).toBe('pending');
    });

    it('should remove upload when UPLOAD_CANCELED event is triggered', async () => {
        const file = createFile();
        const tasks = [createUploadTask('target-123', file)];

        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));
        expect(wrapper.vm.uploads.size).toBe(1);

        wrapper.vm.onUploadEvent(createUploadCanceledEvent('target-123'));

        expect(wrapper.vm.uploads.size).toBe(0);
        expect(wrapper.vm.snackbarItem).toBeNull();
        expect(mockSnackbar.removeSnackbar).toHaveBeenCalledWith('media-upload-status');
    });

    it('should calculate upload progress correctly', async () => {
        const file1 = createFile('test1.jpg', 'a'.repeat(100));
        const file2 = createFile('test2.jpg', 'b'.repeat(100));
        const file3 = createFile('test3.jpg', 'c'.repeat(100));

        wrapper.vm.updateSnackbar = jest.fn();

        const tasks = [
            createUploadTask('target-1', file1),
            createUploadTask('target-2', file2),
            createUploadTask('target-3', file3),
        ];
        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));

        expect(wrapper.vm.uploadProgress).toBe(0);

        wrapper.vm.onUploadEvent(createUploadFinishedEvent('target-1'));
        expect(wrapper.vm.uploadProgress).toBe(33);

        wrapper.vm.onUploadEvent(createUploadFinishedEvent('target-2'));
        expect(wrapper.vm.uploadProgress).toBe(67);

        wrapper.vm.onUploadEvent(createUploadFinishedEvent('target-3'));
        expect(wrapper.vm.uploadProgress).toBe(100);
    });

    it('should update upload progress when UPLOAD_PROGRESS event is triggered', async () => {
        const file1 = createFile('test1.jpg', 'a'.repeat(100));
        const file2 = createFile('test2.jpg', 'b'.repeat(100));

        const tasks = [
            createUploadTask('target-1', file1),
            createUploadTask('target-2', file2),
        ];
        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));

        expect(wrapper.vm.uploadProgress).toBe(0);

        wrapper.vm.onUploadEvent(createUploadProgressEvent('target-1', 50, 100));
        expect(wrapper.vm.uploadProgress).toBe(25);

        wrapper.vm.onUploadEvent(createUploadProgressEvent('target-1', 100, 100));
        expect(wrapper.vm.uploadProgress).toBe(50);
    });

    it('should not complete upload when progress reaches 100 but response is pending', async () => {
        const file = createFile('test.jpg', 'content');
        const tasks = [createUploadTask('target-123', file)];

        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));
        wrapper.vm.onUploadEvent(createUploadProgressEvent('target-123', file.size, file.size));

        expect(wrapper.vm.uploadProgress).toBe(100);
        expect(wrapper.vm.uploadComplete).toBe(false);
    });

    it('should use singular snackbar message for a single upload', async () => {
        const file = createFile();
        const tasks = [createUploadTask('target-123', file)];

        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));

        expect(wrapper.vm.$t).toHaveBeenCalledWith('global.sw-media-upload.snackbar.message', {
            count: 1,
            progress: 0,
            processed: 0,
            total: 1,
        });
    });

    it('should include processed count in snackbar message for multiple uploads', async () => {
        const file1 = createFile('test1.jpg', 'content');
        const file2 = createFile('test2.jpg', 'content2');

        const tasks = [
            createUploadTask('target-123', file1),
            createUploadTask('target-456', file2),
        ];
        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));
        wrapper.vm.onUploadEvent(createUploadFinishedEvent('target-123'));

        expect(wrapper.vm.$t).toHaveBeenCalledWith(
            'global.sw-media-upload.snackbar.message',
            expect.objectContaining({
                count: 2,
                processed: 1,
                total: 2,
            }),
        );
    });

    it('should detect upload complete when all uploads finished', async () => {
        wrapper.vm.updateSnackbar = jest.fn();
        const file = createFile();
        const tasks = [createUploadTask('target-123', file)];

        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));
        expect(wrapper.vm.uploadComplete).toBe(false);

        wrapper.vm.onUploadEvent(createUploadFinishedEvent('target-123'));
        expect(wrapper.vm.uploadComplete).toBe(true);
    });

    it('should detect upload complete when all uploads failed', async () => {
        wrapper.vm.updateSnackbar = jest.fn();
        const file = createFile();
        const tasks = [createUploadTask('target-123', file)];

        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));
        expect(wrapper.vm.uploadComplete).toBe(false);

        const error = createError('CONTENT__MEDIA_ILLEGAL_FILE_NAME', 'Illegal filename');
        wrapper.vm.onUploadEvent(createUploadFailedEvent('target-123', 'test.jpg', error));

        expect(wrapper.vm.uploadComplete).toBe(true);
    });

    it('should update snackbar config with success state when all uploads complete', async () => {
        wrapper.vm.updateSnackbar = jest.fn();
        const file = createFile();
        const tasks = [createUploadTask('target-123', file)];

        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));
        wrapper.vm.onUploadEvent(createUploadFinishedEvent('target-123'));

        const config = wrapper.vm.snackbarConfig;

        expect(config.uploadState).toBe('success');
        expect(config.errorMessage).toBeUndefined();
    });

    it('should update snackbar config with error state when uploads fail', async () => {
        const file1 = createFile('test.jpg', 'content');
        const file2 = createFile('test2.jpg', 'content2');

        wrapper.vm.updateSnackbar = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();

        const tasks = [
            createUploadTask('target-123', file1),
            createUploadTask('target-456', file2),
        ];
        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));

        const error = createError('CONTENT__MEDIA_ILLEGAL_FILE_NAME', 'Illegal filename');
        wrapper.vm.onUploadEvent(createUploadFailedEvent('target-123', 'test.jpg', error));
        wrapper.vm.onUploadEvent(createUploadFinishedEvent('target-456'));

        const config = wrapper.vm.snackbarConfig;

        expect(config.uploadState).toBe('error');
        expect(config.errorMessage).toBe('2 upload(s) failed');
        expect(wrapper.vm.hasFailedUploads).toBe(true);
    });

    it('should show error notification for client-side request canceled error', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        const error = createClientError('ERR_CANCELED');
        wrapper.vm.showErrorNotification({
            fileName: 'test.jpg',
            error,
        });

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Request canceled: test.jpg',
        });
    });

    it('should show error notification for client-side timeout error', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        const error = createClientError('ECONNABORTED');
        wrapper.vm.showErrorNotification({
            fileName: 'test.jpg',
            error,
        });

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Transport error: test.jpg',
        });
    });

    it('should show error notification for timeout status', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        const error = {
            response: {
                status: 504,
                data: {
                    errors: [],
                },
            },
        };

        wrapper.vm.showErrorNotification({
            fileName: 'test.jpg',
            error,
        });

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Transport error: test.jpg',
        });
    });

    it('should show error notification for payload too large', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        const error = {
            response: {
                status: 413,
                data: {
                    errors: [],
                },
            },
        };

        wrapper.vm.showErrorNotification({
            fileName: 'test.jpg',
            error,
        });

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Payload too large: test.jpg',
        });
    });

    it('should show error notification for network errors without response', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        const error = {
            message: 'Network Error',
        };

        wrapper.vm.showErrorNotification({
            fileName: 'test.jpg',
            error,
        });

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Transport error: test.jpg',
        });
    });

    it('should not show notification for ignored errors', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        const error = createError('CONTENT__MEDIA_DUPLICATED_FILE_NAME', 'Duplicated filename');
        wrapper.vm.showErrorNotification({
            fileName: 'test.jpg',
            error,
        });

        expect(wrapper.vm.createNotificationError).not.toHaveBeenCalled();
    });

    it('should show multiple error notifications for multiple errors', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        const error = {
            response: {
                data: {
                    errors: [
                        {
                            code: 'CONTENT__MEDIA_ILLEGAL_FILE_NAME',
                            status: '400',
                            title: 'Bad Request',
                            detail: 'Illegal filename',
                        },
                        {
                            code: 'CONTENT__MEDIA_FILE_TYPE_NOT_SUPPORTED',
                            status: '400',
                            title: 'Bad Request',
                            detail: 'File type not supported',
                        },
                    ],
                },
            },
        };

        wrapper.vm.showErrorNotification({
            fileName: 'test.jpg',
            error,
        });

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Illegal filename: test.jpg',
        });
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'File type not supported: test.jpg',
        });
    });

    it('should clear uploads after completion', async () => {
        const file = createFile();
        const tasks = [createUploadTask('target-123', file)];

        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));
        wrapper.vm.onUploadEvent(createUploadFinishedEvent('target-123'));

        expect(wrapper.vm.uploads.size).toBe(0);
        expect(wrapper.vm.snackbarItem).toBeNull();
    });

    it('should find upload by targetId', async () => {
        const file = createFile();
        const tasks = [createUploadTask('target-123', file)];

        wrapper.vm.onUploadEvent(createUploadAddedEvent(tasks));

        const found = wrapper.vm.findByTargetId('target-123');

        expect(found).not.toBeNull();
        expect(found.fileInfo.targetId).toBe('target-123');
        expect(found.fileInfo.name).toBe('test.jpg');
    });

    it('should return null when upload not found by targetId', () => {
        const found = wrapper.vm.findByTargetId('non-existent');

        expect(found).toBeNull();
    });

    it('should generate consistent upload IDs', () => {
        const id1 = wrapper.vm.getUploadId('test.jpg', 1024);
        const id2 = wrapper.vm.getUploadId('test.jpg', 1024);
        const id3 = wrapper.vm.getUploadId('test.jpg', 2048);

        expect(id1).toBe(id2);
        expect(id1).not.toBe(id3);
        expect(id1).toBe('test.jpg:1024');
        expect(id3).toBe('test.jpg:2048');
    });
});
