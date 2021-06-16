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

        return this.httpClient
            .post(`/_action/${this.apiEndpoint}`, payload, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default SyncApiService;
