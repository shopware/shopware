import ApiService from './api.service';

class CustomerGroupApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'customer-group', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default CustomerGroupApiService;
