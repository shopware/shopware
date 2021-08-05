import ApiService from '../api.service';

/**
 * Gateway for the API end point "product"
 * @class
 * @extends ApiService
 */
class SearchApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_search') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'searchService';
    }

    search({ term, page = 1, limit = 5, additionalParams = {}, additionalHeaders = {} }) {
        const headers = this.getBasicHeaders(additionalHeaders);
        const params = Object.assign({ page, limit, term }, additionalParams);

        return this.httpClient
            .get(this.getApiBasePath(), {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default SearchApiService;
