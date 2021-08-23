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

    search({ term, page = 1, limit = 5, additionalParams = {}, additionalHeaders = {}, payload = {} }) {
        const headers = this.getBasicHeaders(additionalHeaders);
        const params = Object.assign({ page, limit, term }, additionalParams);

        if (Shopware.Feature.isActive('FEATURE_NEXT_6040') && Object.keys(payload).length > 0) {
            return this.httpClient
                .post(this.getApiBasePath(), payload, { params, headers })
                .then((response) => {
                    return ApiService.handleResponse(response);
                });
        }

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
