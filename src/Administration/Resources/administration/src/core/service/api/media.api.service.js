import ApiService from './api.service';

class MediaApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default MediaApiService;
