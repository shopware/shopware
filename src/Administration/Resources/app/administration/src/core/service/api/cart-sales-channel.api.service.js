import { deepCopyObject } from 'src/core/service/utils/object.utils';
import utils from 'src/core/service/util.service';
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
        const route = `/sales-channel/${salesChannelId}/checkout/cart`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, {}, { additionalParams, headers });
    }

    getCart(salesChannelId, contextToken, additionalParams = {}, additionalHeaders = {}) {
        const route = `/sales-channel/${salesChannelId}/checkout/cart`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.get(route, { additionalParams, headers });
    }

    cancelCart(salesChannelId, contextToken, additionalParams = {}, additionalHeaders = {}) {
        const route = `/sales-channel/${salesChannelId}/checkout/cart`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.delete(route, { additionalParams, headers });
    }

    addProduct(
        salesChannelId,
        contextToken,
        productId,
        quantity,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = `/sales-channel/${salesChannelId}/checkout/cart/product/${productId}`;
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

    addCustomItem(
        salesChannelId,
        contextToken,
        item,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const id = utils.createId();
        const route = `/sales-channel/${salesChannelId}/checkout/cart/line-item/${id}`;

        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        const dummyPrice = deepCopyObject(item.priceDefinition);
        dummyPrice.taxRules = item.priceDefinition.taxRules;
        dummyPrice.quantity = item.quantity;
        dummyPrice.type = 'quantity';

        return this.httpClient.post(route,
            { label: item.label,
                quantity: item.quantity,
                type: item.type,
                description: item.description,
                priceDefinition: dummyPrice,
                salesChannelId: salesChannelId },
            { additionalParams, headers });
    }

    removeLineItems(
        salesChannelId,
        contextToken,
        lineItemKeys,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = `/sales-channel/${salesChannelId}/checkout/cart/line-items/delete`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.post(route, { keys: lineItemKeys }, { additionalParams, headers });
    }

    updateLineItem(
        salesChannelId,
        contextToken,
        item,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = `/sales-channel/${salesChannelId}/checkout/cart/line-item/${item.id}`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        const dummyPrice = deepCopyObject(item.priceDefinition);
        dummyPrice.taxRules = item.priceDefinition.taxRules;
        dummyPrice.quantity = item.quantity;
        dummyPrice.type = 'quantity';

        return this.httpClient.patch(route,
            { label: item.label,
                quantity: item.quantity,
                type: item.type,
                description: item.description,
                priceDefinition: dummyPrice,
                salesChannelId: salesChannelId },
            { additionalParams, headers });
    }
}

export default CartSalesChannelService;
