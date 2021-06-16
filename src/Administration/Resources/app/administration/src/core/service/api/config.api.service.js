import ApiService from '../api.service';

/**
 * Gateway for the API end point "config"
 * @class
 * @extends ApiService
 */
class ConfigApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'config') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'configService';
    }

    /**
     * Get information of the logged in user
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getConfig(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return new Promise((resolve) => {
            this.httpClient
                .get('/_info/config', {
                    params,
                    headers,
                })
                .then((response) => {
                    resolve(ApiService.handleResponse(response));
                });
        });
    }
}

export default ConfigApiService;
