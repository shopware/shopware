import ApiService from './api.service';

/**
 * Gateway for the API end point "customer-group"
 * @class
 * @extends ApiService
 */
class LocaleApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'locale') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default LocaleApiService;
