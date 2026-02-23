/**
 * @sw-package discovery
 */
import Axios from 'axios';
import { fileReader } from 'src/core/service/util.service';
import { UploadEvents } from './media.api.service';
import ApiService from '../api.service';

/**
 * A clean Axios instance without Shopware interceptors (auth, error handling, tracing).
 * Required for direct browser-to-S3 uploads where extra headers would break
 * the presigned URL signature.
 */
const s3Client = Axios.create();

/**
 * Gateway for presigned S3 upload endpoints
 * @class
 * @extends ApiService
 */
class MediaPresignedUploadApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'mediaPresignedUploadService';
    }

    /**
     * Prepare a presigned upload. For new uploads, creates a placeholder media entity.
     * For replace, pass an existing mediaId to reuse the entity.
     *
     * @param {Object} params
     * @param {string} params.fileName - File name without extension
     * @param {string} params.extension - File extension (e.g. 'jpg')
     * @param {string} params.mimeType - MIME type (e.g. 'image/jpeg')
     * @param {string|null} [params.mediaFolderId=null] - Target media folder ID
     * @param {boolean} [params.isPrivate=false] - Whether file is private
     * @param {string|null} [params.mediaId=null] - Existing media ID (replace mode)
     * @returns {Promise<{mediaId: string, url: string, path: string, expiresAt: string}>}
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
     * Upload a file directly to S3 using the presigned URL.
     * Uses a clean Axios instance to avoid Shopware interceptors that would
     * add headers breaking the presigned URL signature.
     *
     * @param {string} presignedUrl - The presigned S3 URL
     * @param {File} file - The file to upload
     * @param {string} mimeType - The MIME type of the file
     * @param {Function|null} [onProgress=null] - Progress callback ({loaded, total})
     * @returns {Promise<void>}
     */
    uploadToPresignedUrl(presignedUrl, file, mimeType, onProgress = null) {
        return s3Client.put(presignedUrl, file, {
            headers: { 'Content-Type': mimeType },
            onUploadProgress: onProgress
                ? (progressEvent) => {
                      onProgress({
                          loaded: progressEvent.loaded,
                          total: progressEvent.total ?? file.size,
                      });
                  }
                : undefined,
            timeout: 0,
        });
    }

    /**
     * Finalize the upload: verify the file exists in S3 and update the media entity.
     *
     * @param {string} mediaId - The media entity ID
     * @param {Object} params
     * @param {string} params.fileName - File name without extension
     * @param {string} params.extension - File extension
     * @param {string} params.mimeType - MIME type
     * @param {string} params.path - The S3 path from prepareUpload response
     * @returns {Promise<{mediaId: string}>}
     */
    finalizeUpload(mediaId, { fileName, extension, mimeType, path }) {
        return this.httpClient
            .post(
                `/_action/media/${mediaId}/finalize-upload`,
                JSON.stringify({
                    fileName,
                    extension,
                    mimeType,
                    path,
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
     * Orchestrate presigned uploads for multiple files: prepare, upload to S3,
     * finalize, and emit lifecycle events.
     *
     * @param {string} uploadTag - The upload tag for event listeners
     * @param {File[]} files - Files to upload
     * @param {Object} options
     * @param {string|null} options.mediaFolderId - Target media folder ID
     * @param {boolean} options.isPrivate - Whether files are private
     * @param {Object} eventBridge - Bridge to the mediaService event system
     * @param {Function} eventBridge.getListeners - Returns listeners for a tag
     * @param {Function} eventBridge.createEvent - Creates an upload event object
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

        return Promise.all(
            files.map(async (fileHandle) => {
                const { fileName, extension } = fileReader.getNameAndExtensionFromFile(fileHandle);
                const mimeType = fileHandle.type || 'application/octet-stream';
                let mediaId = null;
                let result = null;

                try {
                    result = await this.prepareUpload({
                        fileName,
                        extension,
                        mimeType,
                        ...options,
                    });

                    mediaId = result.mediaId;

                    emit(UploadEvents.UPLOAD_ADDED, {
                        data: [{ targetId: mediaId, src: fileHandle }],
                    });

                    await this.uploadToPresignedUrl(result.url, fileHandle, mimeType, (progress) => {
                        emit(UploadEvents.UPLOAD_PROGRESS, {
                            targetId: mediaId,
                            loaded: progress.loaded,
                            total: progress.total,
                        });
                    });

                    await this.finalizeUpload(mediaId, {
                        fileName,
                        extension,
                        mimeType,
                        path: result.path,
                    });

                    successCount += 1;
                    emit(UploadEvents.UPLOAD_FINISHED, {
                        targetId: mediaId,
                        successAmount: successCount,
                        failureAmount: failureCount,
                        totalAmount: totalFiles,
                    });
                } catch (error) {
                    failureCount += 1;
                    emit(UploadEvents.UPLOAD_FAILED, {
                        targetId: mediaId ?? fileHandle.name,
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
            }),
        );
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MediaPresignedUploadApiService;
