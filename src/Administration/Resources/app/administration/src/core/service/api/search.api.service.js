import ApiService from '../api.service';

/**
 * Gateway for the API end point 'product'
 * @class
 * @extends ApiService
 */
class SearchApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_admin') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'searchService';
    }

    elastic(term, entities, limit, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`${this.getApiBasePath()}/es-search`, { term, limit, entities }, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
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
            .post(`${this.getApiBasePath()}/search`, queries, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SearchApiService;
