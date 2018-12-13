import ApiService from './api.service';

/**
 * Gateway for the API end point "media-folder-configuration"
 * @class
 * @extends ApiService
 */
class MediaFolderConfigurationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media-folder-configuration') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default MediaFolderConfigurationApiService;
