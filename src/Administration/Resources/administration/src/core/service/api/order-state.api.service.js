import ApiService from './api.service';

/**
 * Gateway for the API end point "order-state"
 * @class
 * @extends ApiService
 */
class OrderStateApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order-state') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default OrderStateApiService;
