import ApiService from './api.service';

class OrderLineItemApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order-line-item', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }

    getList(offset = 0, limit = 25, orderUuid) {
        if (!orderUuid) {
            return Promise.reject(new Error('Missing required argument: orderUuid'));
        }

        const queryString = JSON.stringify({
            type: 'nested',
            queries: [
                {
                    type: 'term',
                    field: 'order_line_item.orderUuid',
                    value: orderUuid
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

export default OrderLineItemApiService;
