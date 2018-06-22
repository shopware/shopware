import ApiService from './api.service';

/**
 * Gateway for the API end point "customer_address"
 * @class
 * @extends ApiService
 */
class CustomerAddressApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'customer_address') {
        super(httpClient, loginService, apiEndpoint);
    }

    getListByCustomerId(customerId, offset, limit, additionalParams = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);
        let params = {};

        if (offset >= 0) {
            params.offset = offset;
        }

        if (limit > 0) {
            params.limit = limit;
        }

        params = Object.assign(params, additionalParams);

        const url = this.getApiBasePath(customerId, 'customer');
        console.log(url);

        // Switch to the general search end point when we're having a search term
        if ((params.term && params.term.length) || (params.filter && params.filter.length)) {
            return this.httpClient
                .post(`${this.getApiBasePath(null, 'search')}`, params, { headers })
                .then((response) => {
                    return ApiService.handleResponse(response);
                });
        }

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
export default CustomerAddressApiService;
