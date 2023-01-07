import ApiService from '../api.service';

/**
 * Gateway for the API end point 'product'
 * @class
 * @extends ApiService
 */
class SearchApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_admin/search') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'searchService';
    }

    /**
     *
     * @param {object} queries
     * @param {object} additionalHeaders
     * */
    searchQuery(queries = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        Object.keys(queries).forEach(entity => {
            if (typeof queries[entity].parse === 'function') {
                queries[entity] = queries[entity].parse();
            }
        });

        return this.httpClient
            .post(this.getApiBasePath(), queries, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SearchApiService;
