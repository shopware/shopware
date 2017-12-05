import ApiService from './api.service';

class OrderApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default OrderApiService;
