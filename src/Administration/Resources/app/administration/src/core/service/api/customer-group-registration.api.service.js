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
        if (Shopware.Feature.isActive('FEATURE_NEXT_17261')) {
            const route = `/_action/${this.getApiBasePath()}/accept`;

            return this.httpClient.post(
                route,
                {
                    customerIds: [customerId],
                },
                {
                    params: additionalParams,
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            ).then((response) => {
                return ApiService.handleResponse(response);
            });
        }

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
        if (Shopware.Feature.isActive('FEATURE_NEXT_17261')) {
            const route = `/_action/${this.getApiBasePath()}/decline`;

            return this.httpClient.post(
                route,
                {
                    customerIds: [customerId],
                },
                {
                    params: additionalParams,
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            ).then((response) => {
                return ApiService.handleResponse(response);
            });
        }

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
