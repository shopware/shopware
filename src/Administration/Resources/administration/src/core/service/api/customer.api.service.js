import ApiService from './api.service';

/**
 * Gateway for the API end point "customer"
 * @class
 * @extends ApiService
 */
class CustomerApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'customer') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default CustomerApiService;
