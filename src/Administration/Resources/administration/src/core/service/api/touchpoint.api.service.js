import ApiService from './api.service';

/**
 * Gateway for the API end point "application"
 * @class
 * @extends ApiService
 */
class TouchpointApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'touchpoint') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default TouchpointApiService;
