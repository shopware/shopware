import ApiService from '../api.service';

/**
 * Gateway for the API end point "customer_address"
 * @class
 * @extends ApiService
 */
class CustomerAddressApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'customer-address') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'customerAddressService';
    }

    getListByCustomerId(customerId, page, limit, additionalParams = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);
        let params = {};

        if (page >= 1) {
            params.page = page;
        }

        if (limit > 0) {
            params.limit = limit;
        }

        params = Object.assign(params, additionalParams);

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
