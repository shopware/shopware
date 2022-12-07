import { deepCopyObject } from 'src/core/service/utils/object.utils';
import utils from 'src/core/service/util.service';
import type { AxiosInstance } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';
import type { LineItem, CalculatedPrice } from '../../../module/sw-order/order.types';
import { LineItemType, PriceType } from '../../../module/sw-order/order.types';
/**
 * Gateway for the API end point "cart"
 * Uses the _proxy endpoint of the admin api to connect to the store-api endpoint cart
 * @class
 * @extends ApiService
 */
class CartStoreService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'cart') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'cartStoreService';
    }

    mapLineItemTypeToPriceType(itemType: LineItemType): PriceType {
        const mapTypes = {
            [LineItemType.PRODUCT]: PriceType.QUANTITY,
            [LineItemType.CUSTOM]: PriceType.QUANTITY,
            [LineItemType.CREDIT]: PriceType.ABSOLUTE,
        } as Record<LineItemType, PriceType>;

        return mapTypes[itemType];
    }

    createCart(salesChannelId: string, additionalParams = {}, additionalHeaders = {}) {
        return this.getCart(salesChannelId, null, additionalParams, additionalHeaders);
    }

    getCart(salesChannelId: string, contextToken: string|null, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/store-api/${salesChannelId}/checkout/cart`;
        const headers = this.getBasicHeaders({ ...additionalHeaders });
        if (contextToken) {
            headers['sw-context-token'] = contextToken;
        }

        return this.httpClient.get(route, { ...additionalParams, headers });
    }

    cancelCart(salesChannelId: string, contextToken: string, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/store-api/${salesChannelId}/checkout/cart`;
        const headers = this.getBasicHeaders({
            ...additionalHeaders,
            'sw-context-token': contextToken,
        });

        return this.httpClient.delete(route, { ...additionalParams, headers });
    }

    removeLineItems(
        salesChannelId: string,
        contextToken: string,
        lineItemKeys: string[],
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_proxy/store-api/${salesChannelId}/checkout/cart/line-item`;
        const headers = this.getBasicHeaders({
            ...additionalHeaders,
            'sw-context-token': contextToken,
        });

        return this.httpClient.delete(route, { ...additionalParams, headers, data: { ids: lineItemKeys } });
    }

    getRouteForItem(id: string, salesChannelId: string) {
        return `_proxy/store-api/${salesChannelId}/checkout/cart/line-item`;
    }

    shouldPriceUpdated(item: LineItem, isNewProductItem: boolean) {
        const isUnitPriceEdited = item.price?.unitPrice !== item.priceDefinition.price;
        const isTaxRateEdited = (item.price?.taxRules?.[0]?.taxRate ?? null)
            !== (item.priceDefinition?.taxRules?.[0]?.taxRate ?? null);
        const isCustomItem = item.type === LineItemType.CUSTOM;

        const isExistingProductAndUnitPriceIsEdited = !isNewProductItem && isUnitPriceEdited;

        if ((isExistingProductAndUnitPriceIsEdited || isTaxRateEdited) || (isCustomItem && !isUnitPriceEdited)) {
            return true;
        }
        return false;
    }

    getPayloadForItem(item: LineItem, salesChannelId: string, isNewProductItem: boolean, id: string) {
        let dummyPrice = null;
        if (this.shouldPriceUpdated(item, isNewProductItem)) {
            dummyPrice = deepCopyObject(item.priceDefinition);
            dummyPrice.taxRules = item.priceDefinition.taxRules;
            dummyPrice.quantity = item.quantity;
            dummyPrice.type = this.mapLineItemTypeToPriceType(item.type);
        }

        return {
            items: [
                {
                    id: id,
                    referencedId: id,
                    label: item.label,
                    quantity: item.quantity,
                    type: item.type,
                    description: item.description,
                    priceDefinition: dummyPrice,
                    stackable: true,
                    removable: true,
                    salesChannelId,
                },
            ],
        };
    }

    saveLineItem(
        salesChannelId: string,
        contextToken: string,
        item: LineItem,
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const isNewProductItem = item._isNew && item.type === LineItemType.PRODUCT;
        const id = item.identifier || item.id || utils.createId();
        const route = this.getRouteForItem(id, salesChannelId);
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken,
        };

        const payload = this.getPayloadForItem(item, salesChannelId, isNewProductItem, id);

        if (item._isNew) {
            return this.httpClient.post(route, payload, { ...additionalParams, headers });
        }

        return this.httpClient.patch(route, payload, { ...additionalParams, headers });
    }

    addPromotionCode(
        salesChannelId: string,
        contextToken: string,
        code: string,
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_proxy/store-api/${salesChannelId}/checkout/cart/line-item`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken,
        };

        const payload = {
            items: [
                {
                    type: LineItemType.PROMOTION,
                    referencedId: code,
                },
            ],
        };

        return this.httpClient.post(route, payload, { ...additionalParams, headers });
    }

    modifyShippingCosts(
        salesChannelId: string,
        contextToken: string,
        shippingCosts: CalculatedPrice,
        additionalHeaders = {},
        additionalParams = {},
    ) {
        const route = '_proxy/modify-shipping-costs';
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken,
        };

        return this.httpClient.patch(route, { salesChannelId, shippingCosts }, { ...additionalParams, headers });
    }


    disableAutomaticPromotions(
        contextToken: string,
        additionalParams: { salesChannelId: string|null } = { salesChannelId: null },
        additionalHeaders = {},
    ) {
        const route = '_proxy/disable-automatic-promotions';
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken,
        };

        const data = {
            salesChannelId: additionalParams.salesChannelId,
        };

        return this.httpClient.patch(route, data, { ...additionalParams, headers });
    }

    enableAutomaticPromotions(
        contextToken: string,
        additionalParams: { salesChannelId: string|null } = { salesChannelId: null },
        additionalHeaders = {},
    ) {
        const route = '_proxy/enable-automatic-promotions';
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken,
        };

        const data = {
            salesChannelId: additionalParams.salesChannelId,
        };

        return this.httpClient.patch(route, data, { ...additionalParams, headers });
    }

    addMultipleLineItems(
        salesChannelId: string,
        contextToken: string,
        items: LineItem[],
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_proxy/store-api/${salesChannelId}/checkout/cart/line-item`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken,
        };

        const payload = items.map(item => {
            if (item.type === LineItemType.PROMOTION) {
                return item;
            }

            const id = item.identifier || item.id || utils.createId();

            return {
                id,
                referencedId: id,
                label: item.label,
                quantity: item.quantity,
                type: item.type,
                description: item.description,
                priceDefinition: item.type === LineItemType.PRODUCT ? null : item.priceDefinition,
                stackable: true,
                removable: true,
                salesChannelId,
            };
        });

        return this.httpClient.post(route, { items: payload }, { ...additionalParams, headers });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default CartStoreService;
