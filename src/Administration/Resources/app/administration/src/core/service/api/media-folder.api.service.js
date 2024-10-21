import ApiService from '../api.service';

/**
 * Gateway for the API end point "media-folder"
 * @class
 * @extends ApiService
 */
class MediaFolderApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media-folder') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'mediaFolderService';
    }

    dissolveFolder(id) {
        const apiRoute = `/_action/${this.getApiBasePath(id)}/dissolve`;
        return this.httpClient
            .post(apiRoute, '', {
                params: {},
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    moveFolder(id, targetId) {
        if (targetId) {
            targetId = `/${targetId}`;
        }
        const apiRoute = `/_action/${this.getApiBasePath(id)}/move${targetId}`;
        return this.httpClient
            .post(apiRoute, '', {
                params: {},
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MediaFolderApiService;
