/**
 * @sw-package discovery
 */
import Axios from 'axios';
import { fileReader } from 'src/core/service/util.service';
import { UploadEvents } from './media.api.service';
import ApiService from '../api.service';

const s3Client = Axios.create();

/**
 * Text-based MIME types that must be served with an explicit `charset=utf-8`, otherwise browsers render
 * multi-byte characters (ä, ö, ü, ß, …) as mojibake when the object is served directly from remote storage/CDN.
 *
 * IMPORTANT: This is the client-side mirror of `FileInfoHelper::TEXT_BASED_MIME_TYPES` in
 * src/Core/Content/Media/File/FileInfoHelper.php. In the presigned direct-to-remote-storage flow the `Content-Type`
 * header sent on the PUT must match the value the server presigned byte-for-byte, or the remote storage rejects the
 * upload with a signature mismatch. Keep both lists in sync.
 */
const TEXT_BASED_MIME_TYPES = [
    'text/plain',
    'text/csv',
    'text/html',
    'text/xml',
    'application/json',
    'application/xml',
];

/**
 * Mirror of `FileInfoHelper::addCharset()` (PHP). Must produce the identical string for the presign signature.
 *
 * @param {string} mimeType
 * @returns {string}
 */
function withCharset(mimeType) {
    return TEXT_BASED_MIME_TYPES.includes(mimeType) ? `${mimeType}; charset=utf-8` : mimeType;
}

/**
 * @param {File} file
 * @param {function({loaded: number, total: number}): void|null} onProgress
 * @returns {function(ProgressEvent): void|undefined}
 */
function createUploadProgressHandler(file, onProgress) {
    if (!onProgress) {
        return undefined;
    }

    return (progressEvent) => {
        onProgress({ loaded: progressEvent.loaded, total: progressEvent.total ?? file.size });
    };
}

/**
 * @class
 * @extends ApiService
 */
class MediaPresignedUploadApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'mediaPresignedUploadService';
    }

    /**
     * @returns {Promise<{mediaId: EntityKey<'media'>, url: string, path: string, expiresAt: string, isDuplicate: boolean}>}
     */
    prepareUpload({ fileName, extension, mimeType, mediaFolderId = null, isPrivate = false, mediaId = null }) {
        return this.httpClient
            .post(
                '/_action/media/presign-upload',
                JSON.stringify({
                    fileName,
                    extension,
                    mimeType,
                    mediaFolderId,
                    private: isPrivate,
                    mediaId,
                }),
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * @returns {Promise<void>}
     */
    uploadToPresignedUrl(presignedUrl, file, mimeType, onProgress = null) {
        return s3Client.put(presignedUrl, file, {
            // Must match the ContentType the server presigned byte-for-byte (see withCharset above).
            headers: { 'Content-Type': withCharset(mimeType) },
            onUploadProgress: createUploadProgressHandler(file, onProgress),
            timeout: 0,
        });
    }

    /**
     * @returns {Promise<{mediaId: EntityKey<'media'>}>}
     */
    finalizeUpload(mediaId, { fileName, extension, mimeType, path, width = null, height = null }) {
        const body = { fileName, extension, mimeType, path };

        if (width !== null && height !== null) {
            body.width = width;
            body.height = height;
        }

        return this.httpClient
            .post(`/_action/media/${mediaId}/finalize-upload`, JSON.stringify(body), {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * @returns {Promise<{id: string, uploadToken: string, upload: {method: string, url: string, headers: Object.<string, string>, expiresAt: string}}>}
     */
    requestUpload({ fileName, mimeType, id = null, mediaFolderId = null, isPrivate = false, deduplicate = false }) {
        const body = { fileName, mimeType, private: isPrivate };

        if (id !== null) {
            body.id = id;
        }

        if (mediaFolderId !== null) {
            body.mediaFolderId = mediaFolderId;
        }

        if (deduplicate) {
            body.deduplicate = deduplicate;
        }

        return this.httpClient
            .post('/_action/media/upload/presign', JSON.stringify(body), {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * @returns {Promise<void>}
     */
    uploadToTicket(upload, file, onProgress = null) {
        return s3Client.request({
            method: upload.method,
            url: upload.url,
            data: file,
            headers: upload.headers,
            onUploadProgress: createUploadProgressHandler(file, onProgress),
            timeout: 0,
        });
    }

    /**
     * @returns {Promise<{id: string}>}
     */
    confirmUpload({ uploadToken, width = null, height = null }) {
        const body = { uploadToken };

        if (width !== null && height !== null) {
            body.width = width;
            body.height = height;
        }

        return this.httpClient
            .post('/_action/media/upload/confirm', JSON.stringify(body), {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Resolves image dimensions from a File using the browser's native decoding.
     * Returns null for non-image files.
     *
     * @returns {Promise<{width: number, height: number}|null>}
     */
    getImageDimensions(file) {
        if (!file.type || !file.type.startsWith('image/')) {
            return Promise.resolve(null);
        }

        const svgTypes = ['image/svg+xml'];
        if (svgTypes.includes(file.type)) {
            return Promise.resolve(null);
        }

        return new Promise((resolve) => {
            const url = URL.createObjectURL(file);
            const img = new Image();

            img.onload = () => {
                resolve({ width: img.naturalWidth, height: img.naturalHeight });
                URL.revokeObjectURL(url);
            };

            img.onerror = () => {
                resolve(null);
                URL.revokeObjectURL(url);
            };

            img.src = url;
        });
    }

    /**
     * `params` are passed on to the request, so a `fileName` there overrides the file's own name.
     *
     * @returns {Promise<EntityKey<'media'>>} the id of the confirmed media
     */
    async uploadFile(file, params = {}, { onRequested = null, onProgress = null } = {}) {
        const [uploadTicket, dimensions] = await Promise.all([
            this.requestUpload({
                fileName: file.name,
                mimeType: file.type || 'application/octet-stream',
                ...params,
            }),
            this.getImageDimensions(file),
        ]);

        onRequested?.(uploadTicket.id);

        await this.uploadToTicket(uploadTicket.upload, file, onProgress);

        const confirmedMedia = await this.confirmUpload({
            uploadToken: uploadTicket.uploadToken,
            width: dimensions?.width ?? null,
            height: dimensions?.height ?? null,
        });

        return confirmedMedia.id;
    }

    /**
     * @returns {Promise<void>}
     */
    runUploads(uploadTag, files, options, { getListeners, createEvent }) {
        const totalFiles = files.length;
        let successCount = 0;
        let failureCount = 0;

        const emit = (action, payload) => {
            getListeners(uploadTag).forEach((listener) => {
                listener(createEvent(action, uploadTag, payload));
            });
        };

        const uploadAndReport = async (fileHandle) => {
            let requestedMediaId = null;

            try {
                const confirmedMediaId = await this.uploadFile(fileHandle, options, {
                    onRequested: (mediaId) => {
                        requestedMediaId = mediaId;
                        emit(UploadEvents.UPLOAD_ADDED, { data: [{ targetId: mediaId, src: fileHandle }] });
                    },
                    onProgress: ({ loaded, total }) => {
                        emit(UploadEvents.UPLOAD_PROGRESS, { targetId: requestedMediaId, loaded, total });
                    },
                });

                successCount += 1;
                emit(UploadEvents.UPLOAD_FINISHED, {
                    targetId: confirmedMediaId,
                    successAmount: successCount,
                    failureAmount: failureCount,
                    totalAmount: totalFiles,
                });
            } catch (error) {
                failureCount += 1;
                const { fileName, extension } = fileReader.getNameAndExtensionFromFile(fileHandle);
                emit(UploadEvents.UPLOAD_FAILED, {
                    targetId: requestedMediaId ?? fileHandle.name,
                    fileName,
                    extension,
                    src: fileHandle,
                    isPrivate: options.isPrivate ?? false,
                    uploadTag,
                    error,
                    successAmount: successCount,
                    failureAmount: failureCount,
                    totalAmount: totalFiles,
                });
            }
        };

        return Promise.all(files.map(uploadAndReport));
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MediaPresignedUploadApiService;
