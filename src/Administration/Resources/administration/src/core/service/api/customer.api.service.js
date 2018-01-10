import ApiService from './api.service';

/**
 * Gateway for the API end point "customer"
 * @class
 * @extends ApiService
 */
class CustomerApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'customer', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default CustomerApiService;
