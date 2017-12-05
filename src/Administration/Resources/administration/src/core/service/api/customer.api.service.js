import ApiService from './api.service';

class CustomerApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'customer', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default CustomerApiService;
