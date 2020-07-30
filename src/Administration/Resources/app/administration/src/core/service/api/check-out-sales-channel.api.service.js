import ApiService from '../api.service';

/**
 * Gateway for the API end point "order"
 * Uses the _proxy endpoint of the admin api to connect to the sales-channel-api endpoint cart
 * @class
 * @extends ApiService
 * @deprecated tag:v6.4.0 - Use CheckoutStoreService
 */
class CheckOutSalesChannelService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'checkout') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'checkOutSalesChannelService';
    }

    checkout(salesChannelId, contextToken, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v${this.getApiVersion()}/checkout/order`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };
        return this.httpClient
            .post(route, {}, { additionalParams, headers });
    }
}

export default CheckOutSalesChannelService;
