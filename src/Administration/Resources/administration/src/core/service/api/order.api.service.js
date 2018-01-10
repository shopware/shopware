import ApiService from './api.service';

/**
 * Gateway for the API end point "order"
 * @class
 * @extends ApiService
 */
class OrderApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default OrderApiService;
