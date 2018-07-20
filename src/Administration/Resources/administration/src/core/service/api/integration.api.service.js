import ApiService from './api.service';

/**
 * Gateway for the API end point "integration"
 * @class
 * @extends ApiService
 */
class IntegrationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'integration') {
        super(httpClient, loginService, apiEndpoint);
    }

    /**
     * Get the generated access key and secret access key from the API
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    generateKey(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get(`${this.getApiBasePath()}/actions/generate-key`, {
                params,
                headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default IntegrationApiService;
