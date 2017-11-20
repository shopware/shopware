import ApiService from './api.service';

class CustomerGroupApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'customer-group', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default CustomerGroupApiService;
