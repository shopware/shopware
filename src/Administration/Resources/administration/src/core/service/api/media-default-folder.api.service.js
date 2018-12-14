import ApiService from './api.service';

/**
 * Gateway for the API end point "media-default-folder"
 * @class
 * @extends ApiService
 */
class MediaDefaultFolderApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media-default-folder') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default MediaDefaultFolderApiService;
