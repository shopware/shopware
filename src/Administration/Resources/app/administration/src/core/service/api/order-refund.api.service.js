import ApiService from '../api.service';

/**
 * Gateway for the API end point "order-refund"
 * @class
 * @extends ApiService
 */
class OrderRefundApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order-refund') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'orderRefundService';
    }

    process(orderRefundId, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/order-refund/${orderRefundId}/process`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, {}, { additionalParams, headers });
    }
}

export default OrderRefundApiService;
