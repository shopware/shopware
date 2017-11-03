import ApiService from './api.service';

class OrderStateApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'orderState', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default OrderStateApiService;
