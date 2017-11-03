import ApiService from './api.service';

class CustomerGroupApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'customerGroup', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default CustomerGroupApiService;
