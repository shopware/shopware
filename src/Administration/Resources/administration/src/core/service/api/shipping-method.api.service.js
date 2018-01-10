import ApiService from './api.service';

/**
 * Gateway for the API end point "shipping-method"
 * @class
 * @extends ApiService
 */
class ShippingMethodApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'shipping-method', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default ShippingMethodApiService;
