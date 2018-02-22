import ApiService from './api.service';

/**
 * Gateway for the API end point "order"
 * @class
 * @extends ApiService
 */
class OrderApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default OrderApiService;
