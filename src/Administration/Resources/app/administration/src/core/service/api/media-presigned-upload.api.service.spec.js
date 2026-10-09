/**
 * @sw-package discovery
 */
import Axios from 'axios';
import MediaPresignedUploadApiService from 'src/core/service/api/media-presigned-upload.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

jest.mock('axios', () => {
    const mockPut = jest.fn().mockResolvedValue({});
    const mockRequest = jest.fn().mockResolvedValue({});
    return {
        __esModule: true,
        default: {
            ...jest.requireActual('axios'),
            create: jest.fn(() => ({ put: mockPut, request: mockRequest })),
        },
    };
});

function getMediaPresignedUploadApiService(client = null, loginService = null) {
    if (client === null) {
        client = createHTTPClient();
    }

    if (loginService === null) {
        loginService = createLoginService(client, Shopware.Context.api);
    }

    return new MediaPresignedUploadApiService(client, loginService);
}

describe('mediaPresignedUploadService', () => {
    it('is registered correctly', () => {
        const service = getMediaPresignedUploadApiService();
        expect(service).toBeInstanceOf(MediaPresignedUploadApiService);
        expect(service.name).toBe('mediaPresignedUploadService');
    });

    it('prepareUpload sends correct payload', async () => {
        const service = getMediaPresignedUploadApiService();
        const postSpy = jest.spyOn(service.httpClient, 'post').mockResolvedValue({
            data: {
                mediaId: 'media-123',
                url: 'https://s3.example.com/presigned',
                path: 'media/ab/cd/test.jpg',
                expiresAt: '2026-02-10T12:00:00+00:00',
            },
        });

        const result = await service.prepareUpload({
            fileName: 'test',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            mediaFolderId: 'folder-123',
            isPrivate: false,
        });

        expect(postSpy).toHaveBeenCalledWith(
            '/_action/media/presign-upload',
            JSON.stringify({
                fileName: 'test',
                extension: 'jpg',
                mimeType: 'image/jpeg',
                mediaFolderId: 'folder-123',
                private: false,
                mediaId: null,
            }),
            expect.objectContaining({ headers: expect.any(Object) }),
        );
        expect(result.mediaId).toBe('media-123');
        expect(result.url).toBe('https://s3.example.com/presigned');
    });

    it('uploadToPresignedUrl uses clean Axios client with correct config', async () => {
        const s3Client = Axios.create();
        const service = getMediaPresignedUploadApiService();
        const file = new File(['content'], 'test.jpg', { type: 'image/jpeg' });

        await service.uploadToPresignedUrl('https://s3.example.com/presigned', file, 'image/jpeg');

        expect(s3Client.put).toHaveBeenCalledWith(
            'https://s3.example.com/presigned',
            file,
            expect.objectContaining({
                headers: { 'Content-Type': 'image/jpeg' },
                timeout: 0,
            }),
        );
    });

    it('uploadToPresignedUrl appends charset=utf-8 for text-based mime types', async () => {
        const s3Client = Axios.create();
        const service = getMediaPresignedUploadApiService();
        const file = new File(['Schöppingen'], 'umlauts.txt', { type: 'text/plain' });

        await service.uploadToPresignedUrl('https://s3.example.com/presigned', file, 'text/plain');

        expect(s3Client.put).toHaveBeenCalledWith(
            'https://s3.example.com/presigned',
            file,
            expect.objectContaining({
                headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                timeout: 0,
            }),
        );
    });

    it('uploadToPresignedUrl passes progress callback as onUploadProgress', async () => {
        const s3Client = Axios.create();
        const service = getMediaPresignedUploadApiService();
        const file = new File(['content'], 'test.jpg', { type: 'image/jpeg' });
        const onProgress = jest.fn();

        await service.uploadToPresignedUrl('https://s3.example.com/presigned', file, 'image/jpeg', onProgress);

        expect(s3Client.put).toHaveBeenCalledWith(
            'https://s3.example.com/presigned',
            file,
            expect.objectContaining({
                onUploadProgress: expect.any(Function),
            }),
        );
    });

    it('finalizeUpload sends correct payload without dimensions', async () => {
        const service = getMediaPresignedUploadApiService();
        const postSpy = jest.spyOn(service.httpClient, 'post').mockResolvedValue({
            data: { mediaId: 'media-123' },
        });

        const result = await service.finalizeUpload('media-123', {
            fileName: 'test',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: 'media/ab/cd/test.jpg',
        });

        expect(postSpy).toHaveBeenCalledWith(
            '/_action/media/media-123/finalize-upload',
            JSON.stringify({
                fileName: 'test',
                extension: 'jpg',
                mimeType: 'image/jpeg',
                path: 'media/ab/cd/test.jpg',
            }),
            expect.objectContaining({ headers: expect.any(Object) }),
        );
        expect(result.mediaId).toBe('media-123');
    });

    it('finalizeUpload includes dimensions when provided', async () => {
        const service = getMediaPresignedUploadApiService();
        const postSpy = jest.spyOn(service.httpClient, 'post').mockResolvedValue({
            data: { mediaId: 'media-123' },
        });

        await service.finalizeUpload('media-123', {
            fileName: 'test',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: 'media/ab/cd/test.jpg',
            width: 1920,
            height: 1080,
        });

        expect(postSpy).toHaveBeenCalledWith(
            '/_action/media/media-123/finalize-upload',
            JSON.stringify({
                fileName: 'test',
                extension: 'jpg',
                mimeType: 'image/jpeg',
                path: 'media/ab/cd/test.jpg',
                width: 1920,
                height: 1080,
            }),
            expect.objectContaining({ headers: expect.any(Object) }),
        );
    });

    it('getImageDimensions returns null for non-image files', async () => {
        const service = getMediaPresignedUploadApiService();
        const file = new File(['content'], 'doc.pdf', { type: 'application/pdf' });

        const result = await service.getImageDimensions(file);
        expect(result).toBeNull();
    });

    it('getImageDimensions returns null for SVG files', async () => {
        const service = getMediaPresignedUploadApiService();
        const file = new File(['<svg></svg>'], 'icon.svg', { type: 'image/svg+xml' });

        const result = await service.getImageDimensions(file);
        expect(result).toBeNull();
    });

    it('requestUpload sends params and returns a ticket', async () => {
        const service = getMediaPresignedUploadApiService();
        const postSpy = jest.spyOn(service.httpClient, 'post').mockResolvedValue({
            data: {
                id: 'media-123',
                uploadToken: 'signed.token',
                upload: {
                    method: 'PUT',
                    url: 'https://s3.example.com/presigned',
                    headers: { 'Content-Type': 'image/jpeg' },
                    expiresAt: '2026-02-10T12:00:00+00:00',
                },
            },
        });

        const ticket = await service.requestUpload({
            fileName: 'test.jpg',
            mimeType: 'image/jpeg',
            mediaFolderId: 'folder-123',
            isPrivate: true,
        });

        expect(postSpy).toHaveBeenCalledWith(
            '/_action/media/upload/presign',
            JSON.stringify({
                fileName: 'test.jpg',
                mimeType: 'image/jpeg',
                private: true,
                mediaFolderId: 'folder-123',
            }),
            expect.objectContaining({ headers: expect.any(Object) }),
        );
        expect(ticket.id).toBe('media-123');
        expect(ticket.uploadToken).toBe('signed.token');
    });

    it.each([
        {
            scenario: 'a new upload with defaults',
            params: { fileName: 'test.jpg', mimeType: 'image/jpeg' },
            expectedBody: { fileName: 'test.jpg', mimeType: 'image/jpeg', private: false },
        },
        {
            scenario: 'a replace that deduplicates',
            params: { fileName: 'test.jpg', mimeType: 'image/jpeg', id: 'media-123', deduplicate: true },
            expectedBody: {
                fileName: 'test.jpg',
                mimeType: 'image/jpeg',
                private: false,
                id: 'media-123',
                deduplicate: true,
            },
        },
    ])('requestUpload sends only the given optional params for $scenario', async ({ params, expectedBody }) => {
        const service = getMediaPresignedUploadApiService();
        const postSpy = jest.spyOn(service.httpClient, 'post').mockResolvedValue({ data: {} });

        await service.requestUpload(params);

        expect(postSpy).toHaveBeenCalledWith(
            '/_action/media/upload/presign',
            JSON.stringify(expectedBody),
            expect.objectContaining({ headers: expect.any(Object) }),
        );
    });

    it('uploadToTicket reports progress and falls back to the file size as total', async () => {
        const s3Client = Axios.create();
        const service = getMediaPresignedUploadApiService();
        const file = new File(['content'], 'image.png', { type: 'image/png' });
        const onProgress = jest.fn();

        await service.uploadToTicket(
            { method: 'PUT', url: 'https://s3.example.com/presigned', headers: {} },
            file,
            onProgress,
        );
        s3Client.request.mock.lastCall[0].onUploadProgress({ loaded: 3 });

        expect(onProgress).toHaveBeenCalledWith({ loaded: 3, total: file.size });
    });

    it('uploadToTicket sends the ticket headers verbatim', async () => {
        const s3Client = Axios.create();
        const service = getMediaPresignedUploadApiService();
        const file = new File(['Schöppingen'], 'umlauts.txt', { type: 'text/plain' });
        const upload = {
            method: 'PUT',
            url: 'https://s3.example.com/presigned',
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        };

        await service.uploadToTicket(upload, file);

        expect(s3Client.request).toHaveBeenCalledWith(
            expect.objectContaining({
                method: 'PUT',
                url: 'https://s3.example.com/presigned',
                data: file,
                headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                timeout: 0,
            }),
        );
    });

    it('confirmUpload sends the upload token', async () => {
        const service = getMediaPresignedUploadApiService();
        const postSpy = jest.spyOn(service.httpClient, 'post').mockResolvedValue({
            data: { id: 'media-123' },
        });

        const result = await service.confirmUpload({ uploadToken: 'signed.token' });

        expect(postSpy).toHaveBeenCalledWith(
            '/_action/media/upload/confirm',
            JSON.stringify({ uploadToken: 'signed.token' }),
            expect.objectContaining({ headers: expect.any(Object) }),
        );
        expect(result.id).toBe('media-123');
    });

    it('confirmUpload includes dimensions when provided', async () => {
        const service = getMediaPresignedUploadApiService();
        const postSpy = jest.spyOn(service.httpClient, 'post').mockResolvedValue({
            data: { id: 'media-123' },
        });

        await service.confirmUpload({ uploadToken: 'signed.token', width: 800, height: 600 });

        expect(postSpy).toHaveBeenCalledWith(
            '/_action/media/upload/confirm',
            JSON.stringify({ uploadToken: 'signed.token', width: 800, height: 600 }),
            expect.objectContaining({ headers: expect.any(Object) }),
        );
    });

    it('runUploads confirms with the image dimensions and reports the confirmed media id', async () => {
        const service = getMediaPresignedUploadApiService();
        const file = new File(['content'], 'image.png', { type: 'image/png' });
        const listener = jest.fn();

        jest.spyOn(service, 'requestUpload').mockResolvedValue({
            id: 'requested-id',
            uploadToken: 'signed.token',
            upload: { method: 'PUT', url: 'https://s3.example.com/presigned', headers: {} },
        });
        jest.spyOn(service, 'getImageDimensions').mockResolvedValue({ width: 800, height: 600 });
        jest.spyOn(service, 'uploadToTicket').mockResolvedValue();
        const confirmSpy = jest.spyOn(service, 'confirmUpload').mockResolvedValue({ id: 'existing-id' });

        await service.runUploads(
            'upload-tag',
            [file],
            {},
            {
                getListeners: () => [listener],
                createEvent: (action, uploadTag, payload) => ({ action, uploadTag, payload }),
            },
        );

        expect(confirmSpy).toHaveBeenCalledWith({ uploadToken: 'signed.token', width: 800, height: 600 });
        expect(listener).toHaveBeenLastCalledWith(
            expect.objectContaining({
                action: 'media-upload-finish',
                payload: expect.objectContaining({ targetId: 'existing-id' }),
            }),
        );
    });

    it('uploadFile falls back to a binary type and confirms without dimensions', async () => {
        const service = getMediaPresignedUploadApiService();
        const file = new File(['content'], 'archive.bin');
        const requestSpy = jest.spyOn(service, 'requestUpload').mockResolvedValue({
            id: 'requested-id',
            uploadToken: 'signed.token',
            upload: { method: 'PUT', url: 'https://s3.example.com/presigned', headers: {} },
        });
        jest.spyOn(service, 'getImageDimensions').mockResolvedValue(null);
        jest.spyOn(service, 'uploadToTicket').mockResolvedValue();
        const confirmSpy = jest.spyOn(service, 'confirmUpload').mockResolvedValue({ id: 'requested-id' });

        const confirmedMediaId = await service.uploadFile(file);

        expect(requestSpy).toHaveBeenCalledWith({ fileName: 'archive.bin', mimeType: 'application/octet-stream' });
        expect(confirmSpy).toHaveBeenCalledWith({ uploadToken: 'signed.token', width: null, height: null });
        expect(confirmedMediaId).toBe('requested-id');
    });

    it('runUploads reports upload progress for the requested media id', async () => {
        const service = getMediaPresignedUploadApiService();
        const file = new File(['content'], 'image.png', { type: 'image/png' });
        const listener = jest.fn();

        jest.spyOn(service, 'requestUpload').mockResolvedValue({
            id: 'requested-id',
            uploadToken: 'signed.token',
            upload: { method: 'PUT', url: 'https://s3.example.com/presigned', headers: {} },
        });
        jest.spyOn(service, 'getImageDimensions').mockResolvedValue(null);
        jest.spyOn(service, 'uploadToTicket').mockImplementation((upload, uploadedFile, onProgress) => {
            onProgress({ loaded: 3, total: 7 });

            return Promise.resolve();
        });
        jest.spyOn(service, 'confirmUpload').mockResolvedValue({ id: 'requested-id' });

        await service.runUploads(
            'upload-tag',
            [file],
            {},
            {
                getListeners: () => [listener],
                createEvent: (action, uploadTag, payload) => ({ action, uploadTag, payload }),
            },
        );

        expect(listener).toHaveBeenCalledWith(
            expect.objectContaining({
                action: 'media-upload-progress',
                payload: { targetId: 'requested-id', loaded: 3, total: 7 },
            }),
        );
    });

    it('runUploads reports a failed upload under the file name when no ticket was issued', async () => {
        const service = getMediaPresignedUploadApiService();
        const file = new File(['content'], 'image.png', { type: 'image/png' });
        const listener = jest.fn();
        const error = new Error('Request failed');

        jest.spyOn(service, 'requestUpload').mockRejectedValue(error);
        jest.spyOn(service, 'getImageDimensions').mockResolvedValue(null);

        await service.runUploads(
            'upload-tag',
            [file],
            {},
            {
                getListeners: () => [listener],
                createEvent: (action, uploadTag, payload) => ({ action, uploadTag, payload }),
            },
        );

        expect(listener).toHaveBeenCalledWith(
            expect.objectContaining({
                action: 'media-upload-fail',
                payload: expect.objectContaining({
                    targetId: 'image.png',
                    fileName: 'image',
                    extension: 'png',
                    isPrivate: false,
                    error,
                    failureAmount: 1,
                }),
            }),
        );
    });
});
