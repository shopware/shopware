import ApiService from './api.service';

/**
 * Gateway for the API end point "shop"
 * @class
 * @extends ApiService
 */
class ShopApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'shop') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default ShopApiService;
