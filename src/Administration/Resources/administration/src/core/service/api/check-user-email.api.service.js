import ApiService from '../api.service';

/**
 * Gateway for the API end point "check-email-unique"
 * @class
 * @extends ApiService
 */
class CheckUserEmailApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'check-email-unique') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'checkUserEmailService';
    }

    checkUserEmail({ email, id }, additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);
        const payload = {
            email,
            id
        };

        return this.httpClient
            .post(`/_action/user/${this.apiEndpoint}`, payload, {
                params,
                headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default CheckUserEmailApiService;
