import ApiService from './api.service';

class OrderStateApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'order-state', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default OrderStateApiService;
