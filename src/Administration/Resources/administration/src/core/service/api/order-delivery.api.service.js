import ApiService from './api.service';

class OrderDeliveryApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order-delivery', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }

    getList(offset = 0, limit = 25, orderId) {
        if (!orderId) {
            return Promise.reject(new Error('Missing required argument: orderId'));
        }

        const queryString = JSON.stringify({
            type: 'nested',
            queries: [
                {
                    type: 'term',
                    field: 'order_delivery.orderId',
                    value: orderId
                }
            ]
        });

        return this.httpClient
            .get(`${this.getApiBasePath()}?offset=${offset}&limit=${limit}&query=${queryString}`)
            .then((response) => {
                return response.data;
            });
    }
}

export default OrderDeliveryApiService;
