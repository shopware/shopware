import ApiService from './api.service';

class ProductManufacturerApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'product-manufacturer', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default ProductManufacturerApiService;
