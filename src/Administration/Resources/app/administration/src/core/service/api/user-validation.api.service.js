import ApiService from '../api.service';

/**
 * Gateway for the API end point "check-email-unique"
 * @class
 * @extends ApiService
 * @package services-settings
 */
class UserValidationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'check-email-unique') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userValidationService';
    }

    checkUserEmail({ email, id }, additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);
        const payload = {
            email,
            id,
        };

        return this.httpClient
            .post(`/_action/user/${this.apiEndpoint}`, payload, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    checkUserUsername({ username, id }, additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);
        const payload = {
            username,
            id,
        };

        return this.httpClient
            .post('/_action/user/check-username-unique', payload, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default UserValidationApiService;
