import ApiService from './api.service';

class OrderApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'order', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default OrderApiService;
