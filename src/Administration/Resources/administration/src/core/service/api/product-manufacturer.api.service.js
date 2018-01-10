import ApiService from './api.service';

/**
 * Gateway for the API end point "product-manufacturer"
 * @class
 * @extends ApiService
 */
class ProductManufacturerApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'product-manufacturer', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default ProductManufacturerApiService;
