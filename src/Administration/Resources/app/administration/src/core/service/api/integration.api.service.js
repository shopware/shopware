const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API end point "integration"
 * @class
 * @package system-settings
 * @extends ApiService
 */
class IntegrationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'integration') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'integrationService';
    }

    /**
     * Get the generated access key and secret access key from the API
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    generateKey(additionalParams = {}, additionalHeaders = {}, user = false) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);
        const endpoint = user ? '/_action/access-key/user' : '/_action/access-key/intergration';

        return this.httpClient
            .get(endpoint, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default IntegrationApiService;
