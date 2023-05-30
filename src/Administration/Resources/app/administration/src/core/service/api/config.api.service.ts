/**
 * @package admin
 */

import type { AxiosInstance } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

/**
 * Gateway for the API end point "config"
 * @class
 * @extends ApiService
 * @package system-settings
 */
class ConfigApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'config') {
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
            void this.httpClient
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

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ConfigApiService;
