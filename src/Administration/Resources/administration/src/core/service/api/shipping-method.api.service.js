import ApiService from './api.service';

class ShippingMethodApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'shippingMethod', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default ShippingMethodApiService;
