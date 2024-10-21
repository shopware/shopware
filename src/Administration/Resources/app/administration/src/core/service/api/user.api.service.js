import ApiService from '../api.service';

/**
 * Gateway for the API end point "user"
 * @class
 * @extends ApiService
 * @package services-settings
 */
class UserApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'user') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userService';
    }

    /**
     * Get information of the logged in user
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getUser(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_info/me', {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Update information of the logged in user
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    updateUser(additionalParams = {}, additionalHeaders = {}) {
        const data = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .patch('/_info/me', data, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default UserApiService;
