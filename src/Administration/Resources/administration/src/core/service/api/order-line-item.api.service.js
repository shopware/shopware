import ApiService from './api.service';

/**
 * Gateway for the API end point "order-line-item"
 * @class
 * @extends ApiService
 */
class OrderLineItemApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order-line-item') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default OrderLineItemApiService;
