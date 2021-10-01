import ApiService from '../api.service';

const { Criteria } = Shopware.Data;

/**
 * Gateway for the API end point 'product'
 * @class
 * @extends ApiService
 */
class SearchApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_search') {
        let endpoint = apiEndpoint;

        if (Shopware.Feature.isActive('FEATURE_NEXT_6040')) {
            endpoint = '_admin/search';
        }

        super(httpClient, loginService, endpoint);
        this.name = 'searchService';
    }

    /** @major-deprecated (flag:FEATURE_NEXT_6040) - will deprecated, using searchQuery instead */
    search({ term, page = 1, limit = 5, additionalParams = {}, additionalHeaders = {} }) {
        const headers = this.getBasicHeaders(additionalHeaders);

        if (Shopware.Feature.isActive('FEATURE_NEXT_6040')) {
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

    /**
     * @internal (flag:FEATURE_NEXT_6040)
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
