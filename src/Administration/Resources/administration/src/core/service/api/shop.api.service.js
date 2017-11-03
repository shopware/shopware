import ApiService from './api.service';

class ShopApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'shop', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default ShopApiService;
