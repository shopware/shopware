/**
 * Gateway for the presigned media upload API
 * @class
 * @extends ApiService
 * @sw-package content
 */
const ApiService = Shopware.Classes.ApiService;

class MediaPresignedUploadApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'mediaPresignedUploadService';
    }

    /**
     * Check if presigned upload is supported (reads from Shopware context)
     * @returns {boolean}
     */
    isSupported() {
        return Shopware.Store.get('context').app.config?.settings?.enablePresignedUpload ?? false;
    }

    /**
     * Request a presigned URL for direct upload to S3
     * @param {string} fileName - The file name
     * @param {string} extension - The file extension
     * @param {string|null} mediaId - Optional existing media ID
     * @returns {Promise}
     */
    prepareUpload(fileName, extension, mediaId = null) {
        const apiRoute = '/_action/media/presigned-upload/prepare';
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(apiRoute, {
                fileName,
                extension,
                mediaId,
            }, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Finalize the upload after direct S3 upload
     * @param {string} mediaId - The media ID
     * @param {string} path - The S3 path where file was uploaded
     * @param {string} fileName - The file name
     * @param {string} extension - The file extension
     * @returns {Promise}
     */
    finalizeUpload(mediaId, path, fileName, extension) {
        const apiRoute = '/_action/media/presigned-upload/finalize';
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(apiRoute, {
                mediaId,
                path,
                fileName,
                extension,
            }, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MediaPresignedUploadApiService;

