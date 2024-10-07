import type { AxiosInstance } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

/**
 * @package checkout
 * Gateway for the API end point "order/state-machine"
 * @class
 * @extends ApiService
 */
class OrderStateMachineApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'order') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'orderStateMachineService';
    }

    transitionOrderState(orderId: string, actionName: string, mediaIds = {}, additionalParams = {}, additionalHeaders = {}) {
        const route = `_action/order/${orderId}/state/${actionName}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, mediaIds, {
            ...additionalParams,
            headers,
        });
    }

    transitionOrderTransactionState(
        orderTransactionId: string,
        actionName: string,
        mediaIds = {},
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_action/order_transaction/${orderTransactionId}/state/${actionName}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, mediaIds, {
            ...additionalParams,
            headers,
        });
    }

    transitionOrderDeliveryState(
        orderDeliveryStateId: string,
        actionName: string,
        mediaIds = {},
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_action/order_delivery/${orderDeliveryStateId}/state/${actionName}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, mediaIds, {
            ...additionalParams,
            headers,
        });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default OrderStateMachineApiService;
