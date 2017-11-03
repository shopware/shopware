import ApiService from './api.service';

class ProductManufacturerApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'productManufacturer', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default ProductManufacturerApiService;
