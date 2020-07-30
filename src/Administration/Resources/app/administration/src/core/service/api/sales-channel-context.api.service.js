import ApiService from '../api.service';

/**
 * Gateway for the API end point "sales-channel-context"
 * Uses the _proxy endpoint of the admin api to connect to the sales-channel-api endpoint cart
 * @class
 * @extends ApiService
 * @deprecated tag:v6.4.0 - Use storeContextService
 */
class SalesChannelContextService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sales-channel-context') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'salesChannelContextService';
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
        const route = `_proxy/sales-channel-api/${salesChannelId}/v${this.getApiVersion()}/context`;
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
        const route = `_proxy/sales-channel-api/${salesChannelId}/v${this.getApiVersion()}/${source}`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, {}, { additionalParams, headers });
    }
}

export default SalesChannelContextService;
