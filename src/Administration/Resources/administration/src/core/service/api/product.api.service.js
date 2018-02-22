import ApiService from './api.service';

/**
 * Gateway for the API end point "product"
 * @class
 * @extends ApiService
 */
class ProductApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'product') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default ProductApiService;
