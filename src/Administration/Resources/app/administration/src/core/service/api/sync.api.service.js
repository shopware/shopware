/**
 * @sw-package framework
 */

import ApiService from '../api.service';

/**
 * Gateway for the API end point "sync"
 * @class
 * @extends ApiService
 */
class SyncApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sync') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'syncService';
    }

    sync(payload, additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        const syncPayloadStringified = JSON.stringify(payload, (k, v) => (v === undefined ? null : v));

        return this.httpClient
            .post(`/_action/${this.apiEndpoint}`, syncPayloadStringified, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SyncApiService;
