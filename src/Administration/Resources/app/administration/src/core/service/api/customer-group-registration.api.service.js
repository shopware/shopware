import ApiService from '../api.service';

/**
 * @package checkout
 * Gateway for the API end point "customer-group-registration"
 * @class
 * @extends ApiService
 */
class CustomerGroupRegistrationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'customer-group-registration') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'customerGroupRegistrationService';
    }

    accept(customerId, additionalParams = {}, additionalHeaders = {}, additionalRequest = {}) {
        const route = `/_action/${this.getApiBasePath()}/accept`;
        return this.httpClient
            .post(
                route,
                {
                    customerIds: Array.isArray(customerId) ? customerId : [customerId],
                    ...additionalRequest,
                },
                {
                    params: additionalParams,
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    decline(customerId, additionalParams = {}, additionalHeaders = {}, additionalRequest = {}) {
        const route = `/_action/${this.getApiBasePath()}/decline`;
        return this.httpClient
            .post(
                route,
                {
                    customerIds: Array.isArray(customerId) ? customerId : [customerId],
                    ...additionalRequest,
                },
                {
                    params: additionalParams,
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default CustomerGroupRegistrationApiService;
