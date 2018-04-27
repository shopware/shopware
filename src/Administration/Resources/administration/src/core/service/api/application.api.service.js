import ApiService from './api.service';

/**
 * Gateway for the API end point "application"
 * @class
 * @extends ApiService
 */
class ApplicationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'application') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default ApplicationApiService;
