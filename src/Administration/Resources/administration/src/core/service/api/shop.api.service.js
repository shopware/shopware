import ApiService from './api.service';

class ShopApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'shop', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default ShopApiService;
