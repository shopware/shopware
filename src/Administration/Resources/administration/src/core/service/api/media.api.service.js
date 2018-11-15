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

<<<<<<< 862b075692d703ac1d58421a3d6bc538e36cf760
    uploadMediaById(id, mimeType, data, fileExtension) {
        const apiRoute = `/_action/media/${id}/upload?extension=${fileExtension}`;
=======
    uploadMediaById(id, mimeType, data, extension, fileName = id) {
        const apiRoute = `${this.getApiBasePath(id)}/actions/upload`;
>>>>>>> NEXT-1038 - Use original filename in administration for media
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

<<<<<<< 862b075692d703ac1d58421a3d6bc538e36cf760
    uploadMediaFromUrl(id, url, fileExtension) {
        const apiRoute = `/_action/media/${id}/upload?extension=${fileExtension}`;
=======
    uploadMediaFromUrl(id, url, extension, fileName = id) {
        const apiRoute = `${this.getApiBasePath(id)}/actions/upload`;
>>>>>>> NEXT-1038 - Use original filename in administration for media
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
        const apiRoute = `${this.getApiBasePath(id)}/actions/rename`;
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
}

export default MediaApiService;
