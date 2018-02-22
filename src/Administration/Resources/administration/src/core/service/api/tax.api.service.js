import ApiService from './api.service';

/**
 * Gateway for the API end point "tax"
 * @class
 * @extends ApiService
 */
class TaxApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'tax') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default TaxApiService;
