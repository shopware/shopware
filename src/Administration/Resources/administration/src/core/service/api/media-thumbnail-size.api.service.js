import ApiService from './api.service';

/**
 * Gateway for the API end point "media-thumbnail-size"
 * @class
 * @extends ApiService
 */
class MediaThumbnailSizeApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media-thumbnail-size') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default MediaThumbnailSizeApiService;
