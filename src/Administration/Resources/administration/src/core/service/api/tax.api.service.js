import ApiService from './api.service';

/**
 * Gateway for the API end point "tax"
 * @class
 * @extends ApiService
 */
class TaxApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'tax', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default TaxApiService;
