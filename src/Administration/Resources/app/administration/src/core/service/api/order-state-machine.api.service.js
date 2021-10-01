import ApiService from '../api.service';

/**
 * Gateway for the API end point "order/state-machine"
 * @class
 * @extends ApiService
 */
class OrderStateMachineApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'orderStateMachineService';
    }

    transitionOrderState(orderId, actionName, mediaIds = {}, additionalParams = {}, additionalHeaders = {}) {
        const route = `_action/order/${orderId}/state/${actionName}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(route, mediaIds, {
                additionalParams,
                headers,
            });
    }

    transitionOrderTransactionState(
        orderTransactionId,
        actionName,
        mediaIds = {},
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_action/order_transaction/${orderTransactionId}/state/${actionName}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(route, mediaIds, {
                additionalParams,
                headers,
            });
    }

    transitionOrderDeliveryState(
        orderDeliveryStateId,
        actionName,
        mediaIds = {},
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_action/order_delivery/${orderDeliveryStateId}/state/${actionName}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(route, mediaIds, {
                additionalParams,
                headers,
            });
    }
}

export default OrderStateMachineApiService;
