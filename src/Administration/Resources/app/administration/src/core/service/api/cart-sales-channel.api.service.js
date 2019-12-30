import ApiService from '../api.service';

/**
 * Gateway for the API end point "cart"
 * Uses the _proxy endpoint of the admin api to connect to the sales-channel-api endpoint cart
 * @class
 * @extends ApiService
 */
class CartSalesChannelService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'cart') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'cartSalesChannelService';
    }

    createCart(salesChannelId, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v1/checkout/cart`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, {}, { additionalParams, headers });
    }

    getCart(salesChannelId, contextToken, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v1/checkout/cart`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.get(route, { additionalParams, headers });
    }

    addProduct(
        salesChannelId,
        contextToken,
        productId,
        quantity,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v1/checkout/cart/product/${productId}`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient
            .post(
                route,
                { quantity: quantity },
                { additionalParams, headers }
            );
    }

    addLineItem(
        salesChannelId,
        contextToken,
        id,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v1/checkout/cart/line-item/${id}`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.patch(route, {}, { additionalParams, headers });
    }

    removeLineItem(
        salesChannelId,
        contextToken,
        lineItemKey,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v1/checkout/cart/line-item/${lineItemKey}`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.delete(route, { additionalParams, headers });
    }

    updateLineItem(
        salesChannelId,
        contextToken,
        lineItemKey,
        quantity,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v1/checkout/cart/line-item/${lineItemKey}`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.patch(route, { quantity: quantity }, { additionalParams, headers });
    }
}

export default CartSalesChannelService;
