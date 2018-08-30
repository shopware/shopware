import ApiService from './api.service';

/**
 * Gateway for the API end point "media"
 * @class
 * @extends ApiService
 */
class MediaApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media') {
        super(httpClient, loginService, apiEndpoint);
    }

    uploadMediaById(id, mimeType, data, fileExtension) {
        const apiRoute = `${this.getApiBasePath(id)}/actions/upload?extension=${fileExtension}`;
        const headers = this.getBasicHeaders({ 'Content-Type': mimeType });
        const params = {};

        return this.httpClient.post(
            apiRoute,
            data,
            { params, headers }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default MediaApiService;
