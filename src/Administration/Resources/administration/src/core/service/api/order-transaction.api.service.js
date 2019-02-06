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

    getState(orderTransactionId, versionId, additionalParams = {}, additionalHeaders = {}) {
        const route = `_action/order-transaction/${orderTransactionId}/state`;
        const headers = Object.assign(ApiService.getVersionHeader(versionId), this.getBasicHeaders(additionalHeaders));

        return this.httpClient
            .get(route, {
                additionalParams,
                headers
            });
    }

    transitionState(orderTransactionId, versionId, actionName, additionalParams = {}, additionalHeaders = {}) {
        const route = `_action/order-transaction/${orderTransactionId}/state/${actionName}`;

        const headers = Object.assign(ApiService.getVersionHeader(versionId), this.getBasicHeaders(additionalHeaders));

        return this.httpClient
            .post(route, {}, {
                additionalParams,
                headers
            });
    }
}

export default OrderTransactionApiService;
