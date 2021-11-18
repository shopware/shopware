import ApiService from '../api.service';

const { Criteria } = Shopware.Data;

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

    /* eslint-disable no-unused-vars */
    /** @deprecated tag:v6.5.0 - Will removed, using searchQuery instead */
    search({ term, page = 1, limit = 5, additionalParams = {}, additionalHeaders = {} }) {
        const headers = this.getBasicHeaders(additionalHeaders);

        const criteria = new Criteria();
        criteria.setTerm(term);
        criteria.setLimit(limit);
        criteria.setPage(page);

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

export default SearchApiService;
