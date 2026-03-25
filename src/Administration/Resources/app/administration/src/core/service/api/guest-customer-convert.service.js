import ApiService from '../api.service';

/**
 * @class
 * @extends ApiService
 * @sw-package fundamentals@framework
 */

class GuestCustomerConvertService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '/') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'GuestCustomerConvertService';
    }

    /**
     * @param payload
     * @param additionalParams
     * @param additionalHeaders
     * @returns {Promise<T>}
     */
    convert(customerId, payload, additionalParams = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`/_action/customer-convert/${customerId}`, payload, {
                additionalParams,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((exception) => {
                throw exception;
            });
    }

    /**
     * @param customerId
     * @param payload
     * @param additionalParams
     * @param additionalHeaders
     * @returns {Promise<T>}
     */
    sendMail(customerId, payload, additionalParams = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`/_action/customer-convert/${customerId}`, payload, {
                additionalParams,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((exception) => {
                throw exception;
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default GuestCustomerConvertService;