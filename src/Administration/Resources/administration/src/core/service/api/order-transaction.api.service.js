import ApiService from '../api.service';

/**
 * Gateway for the API end point "order-transaction"
 * @class
 * @extends ApiService
 */
class OrderTransactionApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order-transaction') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'orderTransactionService';
    }

    getState(orderTransactionId, additionalParams = {}, additionalHeaders = {}) {
        const route = `_action/order-transaction/${orderTransactionId}/state`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get(route, {
                additionalParams,
                headers
            });
    }

    transitionState(orderTransactionId, actionName, additionalParams = {}, additionalHeaders = {}) {
        const route = `_action/order-transaction/${orderTransactionId}/state/${actionName}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(route, {}, {
                additionalParams,
                headers
            });
    }
}

export default OrderTransactionApiService;
