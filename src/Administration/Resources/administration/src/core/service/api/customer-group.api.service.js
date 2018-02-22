import ApiService from './api.service';

/**
 * Gateway for the API end point "customer-group"
 * @class
 * @extends ApiService
 */
class CustomerGroupApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'customer-group') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default CustomerGroupApiService;
