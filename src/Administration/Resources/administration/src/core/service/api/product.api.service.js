import ApiService from './api.service';

class ProductApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'product', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default ProductApiService;
