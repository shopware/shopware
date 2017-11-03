import ApiService from './api.service';

class CustomerApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'customer', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default CustomerApiService;
