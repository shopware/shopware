import ApiService from '../api.service';

/**
 * Gateway for the API end point "user"
 * @class
 * @extends ApiService
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
                headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default UserApiService;
