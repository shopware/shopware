import ApiService from './api.service';

/**
 * Gateway for the API end point "shop"
 * @class
 * @extends ApiService
 */
class ShopApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'shop', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default ShopApiService;
