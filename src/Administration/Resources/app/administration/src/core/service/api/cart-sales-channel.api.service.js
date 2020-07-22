import { deepCopyObject } from 'src/core/service/utils/object.utils';
import utils from 'src/core/service/util.service';
import ApiService from '../api.service';

const lineItemConstants = Object.freeze({
    types: Object.freeze({
        PRODUCT: 'product',
        CREDIT: 'credit',
        CUSTOM: 'custom',
        PROMOTION: 'promotion'
    }),

    priceTypes: Object.freeze({
        ABSOLUTE: 'absolute',
        QUANTITY: 'quantity'
    })
});

/**
 * Gateway for the API end point "cart"
 * Uses the _proxy endpoint of the admin api to connect to the sales-channel-api endpoint cart
 * @class
 * @extends ApiService
 * @deprecated tag:v6.4.0 - Use CartStoreApiService
 */
class CartSalesChannelService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'cart') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'cartSalesChannelService';
    }

    getLineItemTypes() {
        return lineItemConstants.types;
    }

    getLineItemPriceTypes() {
        return lineItemConstants.priceTypes;
    }

    mapLineItemTypeToPriceType(itemType) {
        const lineItemTypes = this.getLineItemTypes();
        const priceTypes = this.getLineItemPriceTypes();

        const mapTypes = {
            [lineItemTypes.PRODUCT]: priceTypes.QUANTITY,
            [lineItemTypes.CUSTOM]: priceTypes.QUANTITY,
            [lineItemTypes.CREDIT]: priceTypes.ABSOLUTE
        };

        return mapTypes[itemType];
    }

    createCart(salesChannelId, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v${this.getApiVersion()}/checkout/cart`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, {}, { additionalParams, headers });
    }

    getCart(salesChannelId, contextToken, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v${this.getApiVersion()}/checkout/cart`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.get(route, { additionalParams, headers });
    }

    cancelCart(salesChannelId, contextToken, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v${this.getApiVersion()}/checkout/cart`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.delete(route, { additionalParams, headers });
    }

    removeLineItems(
        salesChannelId,
        contextToken,
        lineItemKeys,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v${this.getApiVersion()}/checkout/cart/line-items/delete`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.post(route, { keys: lineItemKeys }, { additionalParams, headers });
    }

    getRouteForItem(id, salesChannelId, isNewProductItem) {
        if (isNewProductItem) {
            return `_proxy/sales-channel-api/${salesChannelId}/v${this.getApiVersion()}/checkout/cart/product/${id}`;
        }

        return `_proxy/sales-channel-api/${salesChannelId}/v${this.getApiVersion()}/checkout/cart/line-item/${id}`;
    }

    getPayloadForItem(item, salesChannelId, isNewProductItem) {
        if (isNewProductItem) return { quantity: item.quantity };

        const dummyPrice = deepCopyObject(item.priceDefinition);
        dummyPrice.taxRules = item.priceDefinition.taxRules;
        dummyPrice.quantity = item.quantity;
        dummyPrice.type = this.mapLineItemTypeToPriceType(item.type);

        return {
            label: item.label,
            quantity: item.quantity,
            type: item.type,
            description: item.description,
            priceDefinition: dummyPrice,
            stackable: true,
            removable: true,
            salesChannelId
        };
    }

    saveLineItem(
        salesChannelId,
        contextToken,
        item,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const isNewProductItem = item._isNew && item.type === this.getLineItemTypes().PRODUCT;
        const id = item.identifier || item.id || utils.createId();
        const route = this.getRouteForItem(id, salesChannelId, isNewProductItem);
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        const payload = this.getPayloadForItem(item, salesChannelId, isNewProductItem);

        if (item._isNew) {
            return this.httpClient.post(route, payload, { additionalParams, headers });
        }

        return this.httpClient.patch(route, payload, { additionalParams, headers });
    }

    addPromotionCode(
        salesChannelId,
        contextToken,
        code,
        additionalParams = {},
        additionalHeaders = {}
    ) {
        const route = `_proxy/sales-channel-api/${salesChannelId}/v${this.getApiVersion()}/checkout/cart/code/${code}`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.post(route, {}, { additionalParams, headers });
    }

    modifyShippingCosts(salesChannelId, contextToken, shippingCosts, additionalHeaders, additionalParams = {},) {
        const route = '_proxy/modify-shipping-costs';
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.patch(route, { salesChannelId, shippingCosts }, { additionalParams, headers });
    }


    disableAutomaticPromotions(contextToken, additionalParams = {}, additionalHeaders = {}) {
        const route = '_proxy/disable-automatic-promotions';
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.patch(route, {}, { additionalParams, headers });
    }

    enableAutomaticPromotions(contextToken, additionalParams = {}, additionalHeaders = {}) {
        const route = '_proxy/enable-automatic-promotions';
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken
        };

        return this.httpClient.patch(route, {}, { additionalParams, headers });
    }
}

export default CartSalesChannelService;
