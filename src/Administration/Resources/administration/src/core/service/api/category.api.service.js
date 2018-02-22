import ApiService from './api.service';

/**
 * Gateway for the API end point "category"
 * @class
 * @extends ApiService
 */
class CategoryApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'category') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default CategoryApiService;
