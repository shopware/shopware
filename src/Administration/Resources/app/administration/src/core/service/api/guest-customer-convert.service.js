import ApiService from '../api.service';

/**
 * @class
 * @extends ApiService
 * @sw-package checkout
 */

class GuestCustomerConvertService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '/') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'guestCustomerConvertService';
    }

    /**
     * @param customerId
     * @param payload
     * @param additionalParams
     * @param additionalHeaders
     * @returns {Promise<T>}
     */
    async convert(customerId, payload, additionalParams = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        const response = await this.httpClient.post(`/_action/customer-convert/${customerId}`, payload, {
            params: { ...additionalParams },
            headers,
        });
        return ApiService.handleResponse(response);
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default GuestCustomerConvertService;
