import ApiService from '../api.service';

const { Criteria } = Shopware.Data;

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

    /* eslint-disable no-unused-vars */
    /** @deprecated tag:v6.5.0 - Will removed, using searchQuery instead */
    search({ term, page = 1, limit = 5, additionalParams = {}, additionalHeaders = {} }) {
        const headers = this.getBasicHeaders(additionalHeaders);

        const criteria = new Criteria(page, limit);
        criteria.setTerm(term);

        const entities = [
            'landing_page',
            'order',
            'customer',
            'product',
            'category',
            'media',
            'product_manufacturer',
            'tag',
            'cms_page',
        ];

        const queries = {};

        entities.forEach(entity => {
            queries[entity] = criteria;
        });

        return this.searchQuery(queries, additionalHeaders);
    }
    /* eslint-enable no-unused-vars */

    elastic(term, entities, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`${this.getApiBasePath()}/es-search`, { term, entities }, { headers })
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
