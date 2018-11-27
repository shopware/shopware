import ApiService from './api.service';

/**
 * Gateway for the API end point "rule"
 * @class
 * @extends ApiService
 */
class RuleApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'rule') {
        super(httpClient, loginService, apiEndpoint);
    }

    getRuleConditions(id, additionalParams = {}, additionalHeaders = {}) {
        if (!id) {
            return Promise.reject(new Error('Missing required argument: id'));
        }

        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get(`${this.getApiBasePath(id)}/conditions`, {
                params,
                headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default RuleApiService;
