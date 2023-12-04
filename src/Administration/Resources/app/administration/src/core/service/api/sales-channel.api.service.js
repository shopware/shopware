import ApiService from '../api.service';

/**
 * Gateway for the API end point "application"
 * @class
 * @extends ApiService
 * @package buyers-experience
 */
class SalesChannelApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sales-channel') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'salesChannelService';
    }

    /**
     * Get the generated access key and secret access key from the API
     *
     * @param {Object} additionalParams
     * @param {Object} additionalHeaders
     * @returns {Promise<T>}
     */
    generateKey(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_action/access-key/sales-channel', {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SalesChannelApiService;
