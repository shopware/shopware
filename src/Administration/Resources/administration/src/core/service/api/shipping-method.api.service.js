import ApiService from './api.service';

/**
 * Gateway for the API end point "shipping-method"
 * @class
 * @extends ApiService
 */
class ShippingMethodApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'shipping-method') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default ShippingMethodApiService;
