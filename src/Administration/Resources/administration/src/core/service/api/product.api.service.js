import ApiService from './api.service';

/**
 * Gateway for the API end point "product"
 * @class
 * @extends ApiService
 */
class ProductApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'product', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default ProductApiService;
