import ApiService from '../api.service';

/**
 * Gateway for the API end point "sales-channel-context"
 * Uses the _proxy endpoint of the admin api to connect to the store-api endpoint cart
 * @class
 * @extends ApiService
 */
class StoreContextService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sales-channel-context') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contextStoreService';
    }

    updateCustomerContext(
        customerId,
        salesChannelId,
        contextToken,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = '_proxy/switch-customer';
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient
            .patch(
                route,
                { customerId: customerId, salesChannelId: salesChannelId },
                { additionalParams, headers }
            );
    }

    updateContext(
        context,
        salesChannelId,
        contextToken,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = `_proxy/store-api/${salesChannelId}/v${this.getApiVersion()}/context`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient
            .patch(
                route,
                context,
                { additionalParams, headers }
            );
    }

    getContext(salesChannelId, source, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/store-api/${salesChannelId}/v${this.getApiVersion()}/${source}`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, {}, { additionalParams, headers });
    }
}

export default StoreContextService;
