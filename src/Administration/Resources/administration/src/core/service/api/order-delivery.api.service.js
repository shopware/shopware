import ApiService from './api.service';

/**
 * Gateway for the API end point "order-delivery"
 * @class
 * @extends ApiService
 */
class OrderDeliveryApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order-delivery') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default OrderDeliveryApiService;
