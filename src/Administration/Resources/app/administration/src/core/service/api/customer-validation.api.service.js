import ApiService from '../api.service';

/**
 * Gateway for the API end point "check-email-valid"
 * @class
 * @extends ApiService
 */
class CustomerValidationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'check-customer-email-valid') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'customerValidationService';
    }

    checkCustomerEmail(payload, additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`/_admin/${this.apiEndpoint}`, payload, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((exception) => {
                throw exception;
            });
    }
}

export default CustomerValidationApiService;
