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
});
