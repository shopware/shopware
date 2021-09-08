import ApiService from '../api.service';

/**
 * @class
 * @extends ApiService
 */
class AdminIncrementApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'increment') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'adminIncrementApiService';
    }

    /**
     * @returns {Promise<T>}
     */
    increment(payload, cluster = 'frequently-used', additionalParams = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`/_admin/${this.getApiBasePath()}/${cluster}`, payload, {
                additionalParams,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default AdminIncrementApiService;
