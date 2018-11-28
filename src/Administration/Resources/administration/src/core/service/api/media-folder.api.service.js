import ApiService from './api.service';

/**
 * Gateway for the API end point "catalog"
 * @class
 * @extends ApiService
 */
class MediaFolderApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media-folder') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default MediaFolderApiService;
