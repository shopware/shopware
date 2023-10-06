import ApiService from '../api.service';

/**
 * @class
 * @extends ApiService
 * @package system-settings
 */
class UserActivityApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'increment/user_activity') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userActivityApiService';
    }

    /**
     * @param payload
     * @param additionalParams
     * @param additionalHeaders
     * @returns {Promise<T>}
     */
    increment(payload, additionalParams = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post('/_action/increment/user_activity', payload, {
                additionalParams,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * @param additionalParams
     * @param additionalHeaders
     * @returns {Promise<T>}
     */
    getIncrement(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_action/increment/user_activity', {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default UserActivityApiService;
