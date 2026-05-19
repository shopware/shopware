/**
 * @sw-package buyers-experience
 */
import MediaApiService, { UploadEvents } from 'src/core/service/api/media.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

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

function getMediaApiService(client = null, loginService = null) {
    if (client === null) {
        client = createHTTPClient();
    }

    if (loginService === null) {
        loginService = createLoginService(client, Shopware.Context.api);
    }

    return new MediaApiService(client, loginService);
}

describe('storeService', () => {
    it('is registered correctly', async () => {
        expect(getMediaApiService()).toBeInstanceOf(MediaApiService);
    });

    it('handles keeping files', async () => {
        const mediaApiService = getMediaApiService();
        const callback = jest.fn();
        const keepTask = {
            ...uploadTaskMock,
            originalTargetId: 'original-target-id',
        };
        const event = mediaApiService._createUploadEvent('media-upload-finish', keepTask.uploadTag, {
            targetId: keepTask.targetId,
            originalTargetId: keepTask.originalTargetId,
            successAmount: 0,
            failureAmount: 0,
            totalAmount: 0,
            customMessage: 'global.sw-media-upload.notification.assigned.message',
        });
        mediaApiService.addListener(keepTask.uploadTag, callback);

        mediaApiService.keepFile(keepTask.uploadTag, keepTask);

        expect(callback).toHaveBeenCalledWith(event);
    });

    it('uploadMediaById with glb-file extension and detected Content-Type is empty will set Content-Type to `model/gltf-binary`', () => {
        const mediaApiService = getMediaApiService();
        const httpClientPostSpy = jest.spyOn(mediaApiService.httpClient, 'post');

        mediaApiService.uploadMediaById('test', '', {}, 'glb', 'test');

        expect(httpClientPostSpy.mock.calls[0][2].headers['Content-Type']).toBe('model/gltf-binary');
    });

    it('emits upload progress events when onUploadProgress fires', async () => {
        const mediaApiService = getMediaApiService();
        const callback = jest.fn();

        mediaApiService.addListener('upload-tag', callback);

        const httpClientPostSpy = jest
            .spyOn(mediaApiService.httpClient, 'post')
            .mockImplementation((route, data, config) => {
                config.onUploadProgress({
                    loaded: 5,
                    total: 10,
                });

                return Promise.resolve({ data: null });
            });

        await mediaApiService.uploadMediaById('test-id', 'image/png', new ArrayBuffer(10), 'png', 'test', 'upload-tag');

        expect(callback).toHaveBeenCalledWith({
            action: UploadEvents.UPLOAD_PROGRESS,
            uploadTag: 'upload-tag',
            payload: {
                targetId: 'test-id',
                loaded: 5,
                total: 10,
            },
        });

        mediaApiService.removeListener('upload-tag', callback);
        httpClientPostSpy.mockRestore();
    });

    it('test getDefaultFolderId without result', async () => {
        const mediaApiService = getMediaApiService();

        const spyRepository = jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockImplementation(() => {
            return {
                search: async () => {
                    return Promise.resolve([]);
                },
            };
        });

        expect(await mediaApiService.getDefaultFolderId('product_download')).toBeNull();

        spyRepository.mockRestore();
    });

    it('test getDefaultFolderId without folder', async () => {
        const mediaApiService = getMediaApiService();

        const spyRepository = jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockImplementation(() => {
            return {
                search: async () => {
                    return Promise.resolve([
                        {
                            id: 'test',
                        },
                    ]);
                },
            };
        });

        expect(await mediaApiService.getDefaultFolderId('product_download')).toBeNull();

        spyRepository.mockRestore();
    });

    it('test getDefaultFolderId with folder', async () => {
        const mediaApiService = getMediaApiService();

        let searchCount = 0;

        const spyRepository = jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockImplementation(() => {
            return {
                search: async () => {
                    searchCount += 1;

                    return Promise.resolve([
                        {
                            id: 'test',
                            folder: {
                                id: 'product_download_id',
                            },
                        },
                    ]);
                },
            };
        });

        expect(await mediaApiService.getDefaultFolderId('product_download')).toBe('product_download_id');
        expect(await mediaApiService.getDefaultFolderId('product_download')).toBe('product_download_id');
        expect(mediaApiService.cacheDefaultFolder).toMatchObject({
            product_download: 'product_download_id',
        });
        expect(searchCount).toBe(1);

        spyRepository.mockRestore();
    });

    it('limits concurrent uploads to maxConcurrentUploads', async () => {
        const mediaApiService = getMediaApiService();
        mediaApiService.maxConcurrentUploads = 2;

        let concurrent = 0;
        let maxConcurrent = 0;
        const resolvers = [];

        jest.spyOn(mediaApiService, '_startUpload').mockImplementation(() => {
            concurrent += 1;
            maxConcurrent = Math.max(maxConcurrent, concurrent);

            return new Promise((resolve) => {
                resolvers.push(() => {
                    concurrent -= 1;
                    resolve();
                });
            });
        });

        const tag = 'test-concurrency';
        for (let i = 0; i < 6; i++) {
            mediaApiService.addUpload(tag, {
                src: new File([''], `file-${i}.jpg`),
                targetId: `id-${i}`,
                fileName: `file-${i}`,
                extension: 'jpg',
            });
        }

        const uploadPromise = mediaApiService.runUploads(tag);

        // Wait for the initial workers to start
        await new Promise(process.nextTick);

        // Only 2 should be running concurrently
        expect(concurrent).toBe(2);
        expect(maxConcurrent).toBe(2);

        // Resolve first two uploads
        resolvers.shift()();
        resolvers.shift()();
        await new Promise(process.nextTick);

        // Next two should have started
        expect(concurrent).toBe(2);
        expect(maxConcurrent).toBe(2);

        // Resolve remaining uploads
        while (resolvers.length > 0) {
            resolvers.shift()();
            await new Promise(process.nextTick);
        }

        await uploadPromise;
        expect(maxConcurrent).toBe(2);
    });

    it('completes all uploads even with concurrency limit', async () => {
        const mediaApiService = getMediaApiService();
        mediaApiService.maxConcurrentUploads = 3;

        const completed = [];
        jest.spyOn(mediaApiService, '_startUpload').mockImplementation((task) => {
            completed.push(task.targetId);
            return Promise.resolve();
        });

        const tag = 'test-complete-all';
        const totalFiles = 10;
        for (let i = 0; i < totalFiles; i++) {
            mediaApiService.addUpload(tag, {
                src: new File([''], `file-${i}.jpg`),
                targetId: `id-${i}`,
                fileName: `file-${i}`,
                extension: 'jpg',
            });
        }

        await mediaApiService.runUploads(tag);
        expect(completed).toHaveLength(totalFiles);
    });

    it('fires UPLOAD_FINISHED events with correct counts during concurrent uploads', async () => {
        const mediaApiService = getMediaApiService();
        mediaApiService.maxConcurrentUploads = 2;

        jest.spyOn(mediaApiService, '_startUpload').mockResolvedValue();

        const events = [];
        const tag = 'test-events';
        mediaApiService.addListener(tag, (event) => {
            if (event.action === UploadEvents.UPLOAD_FINISHED) {
                events.push(event.payload);
            }
        });

        for (let i = 0; i < 4; i++) {
            mediaApiService.addUpload(tag, {
                src: new File([''], `file-${i}.jpg`),
                targetId: `id-${i}`,
                fileName: `file-${i}`,
                extension: 'jpg',
            });
        }

        await mediaApiService.runUploads(tag);

        expect(events).toHaveLength(4);
        events.forEach((payload) => {
            expect(payload.totalAmount).toBe(4);
        });
        // Last event should have all uploads as successful
        expect(events[events.length - 1].successAmount).toBe(4);
        expect(events[events.length - 1].failureAmount).toBe(0);
    });

    it('continues processing after a failed upload', async () => {
        const mediaApiService = getMediaApiService();
        mediaApiService.maxConcurrentUploads = 2;

        jest.spyOn(mediaApiService, '_startUpload').mockImplementation((task) => {
            if (task.targetId === 'id-1') {
                return Promise.reject(new Error('upload failed'));
            }
            return Promise.resolve();
        });

        const finishedIds = [];
        const failedIds = [];
        const tag = 'test-errors';
        mediaApiService.addListener(tag, (event) => {
            if (event.action === UploadEvents.UPLOAD_FINISHED) {
                finishedIds.push(event.payload.targetId);
            }
            if (event.action === UploadEvents.UPLOAD_FAILED) {
                failedIds.push(event.payload.targetId);
            }
        });

        for (let i = 0; i < 4; i++) {
            mediaApiService.addUpload(tag, {
                src: new File([''], `file-${i}.jpg`),
                targetId: `id-${i}`,
                fileName: `file-${i}`,
                extension: 'jpg',
            });
        }

        await mediaApiService.runUploads(tag);

        expect(failedIds).toEqual(['id-1']);
        expect(finishedIds).toHaveLength(3);
        expect(finishedIds).toContain('id-0');
        expect(finishedIds).toContain('id-2');
        expect(finishedIds).toContain('id-3');
    });

    it('resolves immediately when no uploads are queued', async () => {
        const mediaApiService = getMediaApiService();
        const result = await mediaApiService.runUploads('non-existent-tag');
        expect(result).toBeUndefined();
    });

    it('uses default maxConcurrentUploads of 10', () => {
        const mediaApiService = getMediaApiService();
        expect(mediaApiService.maxConcurrentUploads).toBe(10);
    });

    it('assigns video cover via API route', async () => {
        const mediaApiService = getMediaApiService();
        const httpClientPostSpy = jest.spyOn(mediaApiService.httpClient, 'post').mockResolvedValue({
            data: null,
        });

        await mediaApiService.assignVideoCover('media-id', 'cover-id');

        expect(httpClientPostSpy).toHaveBeenCalledWith(
            '/_action/media/media-id/video-cover',
            JSON.stringify({ coverMediaId: 'cover-id' }),
            expect.objectContaining({
                headers: expect.objectContaining({ Authorization: expect.any(String) }),
            }),
        );
    });
});
