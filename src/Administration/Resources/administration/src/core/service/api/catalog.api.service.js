import ApiService from './api.service';

/**
 * Gateway for the API end point "catalog"
 * @class
 * @extends ApiService
 */
class CatalogApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'catalog') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default CatalogApiService;
