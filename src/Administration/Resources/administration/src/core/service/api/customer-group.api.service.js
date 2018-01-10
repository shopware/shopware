import ApiService from './api.service';

/**
 * Gateway for the API end point "customer-group"
 * @class
 * @extends ApiService
 */
class CustomerGroupApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'customer-group', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default CustomerGroupApiService;
