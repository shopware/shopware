import ApiService from './api.service';

class ProductApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'product', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default ProductApiService;
