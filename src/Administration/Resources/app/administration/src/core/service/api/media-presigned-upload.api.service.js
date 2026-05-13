/**
 * @sw-package discovery
 */
import Axios from 'axios';
import { fileReader } from 'src/core/service/util.service';
import { UploadEvents } from './media.api.service';
import ApiService from '../api.service';

const s3Client = Axios.create();

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
     * @returns {Promise<{mediaId: string, url: string, path: string, expiresAt: string, isDuplicate: boolean}>}
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
     * @returns {Promise<{mediaId: string}>}
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
                    const [
                        prepareResult,
                        dimensions,
                    ] = await Promise.all([
                        this.prepareUpload({
                            fileName,
                            extension,
                            mimeType,
                            ...options,
                        }),
                        this.getImageDimensions(fileHandle),
                    ]);

                    result = prepareResult;
                    mediaId = result.mediaId;

                    if (result.isDuplicate) {
                        throw this.buildDuplicateError(fileName, extension);
                    }

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
                        width: dimensions?.width ?? null,
                        height: dimensions?.height ?? null,
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

    buildDuplicateError(fileName, extension) {
        return {
            response: {
                data: {
                    errors: [
                        {
                            status: '400',
                            code: 'CONTENT__MEDIA_DUPLICATED_FILE_NAME',
                            detail: `A file with the name "${fileName}.${extension}" already exists.`,
                        },
                    ],
                },
            },
        };
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MediaPresignedUploadApiService;
