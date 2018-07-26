import ApiService from './api.service';

/**
 * Gateway for the API end point "product"
 * @class
 * @extends ApiService
 */
class SearchApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'admin/search') {
        super(httpClient, loginService, apiEndpoint);
    }

    search({ term, offset = 0, limit = 25, additionalParams = {}, additionalHeaders = {} }) {
        const headers = this.getBasicHeaders(additionalHeaders);
        const params = Object.assign({ offset, limit, term }, additionalParams);

        return this.httpClient
            .get(this.getApiBasePath(), {
                params,
                headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default SearchApiService;
