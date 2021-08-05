import ApiService from '../api.service';

/**
 * Gateway for the API end point "customer-group-registration"
 * @class
 * @extends ApiService
 */
class CustomerGroupRegistrationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'customer-group-registration') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'customerGroupRegistrationService';
    }

    accept(customerId, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/accept/${customerId}`;

        return this.httpClient.post(
            apiRoute,
            {},
            {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders),
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    decline(customerId, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/decline/${customerId}`;

        return this.httpClient.post(
            apiRoute,
            {},
            {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders),
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default CustomerGroupRegistrationApiService;
