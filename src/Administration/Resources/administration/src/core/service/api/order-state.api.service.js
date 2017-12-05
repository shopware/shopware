import ApiService from './api.service';

class OrderStateApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order-state', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default OrderStateApiService;
