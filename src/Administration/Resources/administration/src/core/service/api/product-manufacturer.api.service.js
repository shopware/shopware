import ApiService from './api.service';

class ProductManufacturerApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'product-manufacturer', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default ProductManufacturerApiService;
