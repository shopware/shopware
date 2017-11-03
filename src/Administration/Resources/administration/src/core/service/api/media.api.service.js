import ApiService from './api.service';

class MediaApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'media', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default MediaApiService;
