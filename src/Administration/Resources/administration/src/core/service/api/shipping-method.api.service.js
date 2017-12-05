import ApiService from './api.service';

class ShippingMethodApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'shipping-method', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default ShippingMethodApiService;
