import ApiService from '../api.service';

/**
 * @class
 * @extends ApiService
 */
class FlowActionApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'actions') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'flowActionService';
    }

    /**
     * Get all actions
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getActions(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_info/flow-actions.json', {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default FlowActionApiService;
