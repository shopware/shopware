import ApiService from '../api.service';

/**
 * Gateway for the API end point "media"
 * @class
 * @extends ApiService
 */
class MediaApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'mediaService';
    }

    uploadMediaById(id, mimeType, data, extension, fileName = id) {
        const apiRoute = `/_action/${this.getApiBasePath(id)}/upload`;
        const headers = this.getBasicHeaders({ 'Content-Type': mimeType });
        const params = {
            extension,
            fileName
        };

        return this.httpClient.post(
            apiRoute,
            data,
            { params, headers }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    uploadMediaFromUrl(id, url, extension, fileName = id) {
        const apiRoute = `/_action/${this.getApiBasePath(id)}/upload`;
        const headers = this.getBasicHeaders({ 'Content-Type': 'application/json' });
        const params = {
            extension,
            fileName
        };

        const body = JSON.stringify({ url });

        return this.httpClient.post(
            apiRoute,
            body,
            { params, headers }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    renameMedia(id, fileName) {
        const apiRoute = `/_action/${this.getApiBasePath(id)}/rename`;
        return this.httpClient.post(
            apiRoute,
            JSON.stringify({
                fileName
            }),
            {
                params: {},
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    provideName(fileName, extension, mediaId = null) {
        const apiRoute = `/_action/${this.getApiBasePath()}/provide-name`;
        return this.httpClient.get(
            apiRoute,
            {
                params: { fileName, extension, mediaId },
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default MediaApiService;
